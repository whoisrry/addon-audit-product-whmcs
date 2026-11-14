<?php
/**
 * Semua query modul. HANYA SELECT melalui WHMCS\Database\Capsule
 * (Illuminate query builder, parameter binding otomatis).
 */

namespace WHMCS\Module\Addon\ProductAudit;

use WHMCS\Database\Capsule;

final class Repository
{
    /** Status standar WHMCS; status lain dijumlah ke "Lainnya" di ringkasan. */
    public const KNOWN_STATUSES = ['Active', 'Suspended', 'Terminated', 'Pending', 'Cancelled', 'Fraud'];

    private const REQUIRED_TABLES = ['tblproducts', 'tblproductgroups', 'tblhosting', 'tblclients', 'tbladmins'];

    /** Batch untuk export (keyset pagination). */
    private const EXPORT_BATCH = 500;

    public static function missingTables(): array
    {
        $schema = Capsule::schema();
        $missing = [];
        foreach (self::REQUIRED_TABLES as $table) {
            if (!$schema->hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * [VERSI] Nama kolom tanggal terminate di tblhosting. WHMCS 8.x memakai
     * `termination_date`; nama alternatif dicek untuk berjaga-jaga.
     * Nilai yang dikembalikan berasal dari daftar tetap ini, bukan dari input.
     */
    public static function terminateColumn(): ?string
    {
        $schema = Capsule::schema();
        foreach (['termination_date', 'terminationdate'] as $column) {
            if ($schema->hasColumn('tblhosting', $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Q1 + Q2: product (semua, atau hanya hidden) per halaman, dan jumlah
     * service per status untuk product di halaman tersebut.
     */
    public static function productPage(bool $hiddenOnly, int $page, int $perPage): array
    {
        $base = Capsule::table('tblproducts as p');
        if ($hiddenOnly) {
            // [VERIFIKASI] tblproducts.hidden bernilai 1 untuk product hidden.
            $base->where('p.hidden', 1);
        }

        $total = (clone $base)->count('p.id');

        $products = (clone $base)
            ->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
            ->select('p.id', 'p.name', 'p.hidden', 'g.name as group_name')
            ->orderBy('p.id')
            ->forPage($page, $perPage)
            ->get()
            ->all();

        $ids = array_map(static fn ($p) => (int) $p->id, $products);

        $counts = [];
        if ($ids !== []) {
            // [VERIFIKASI] tblhosting.packageid -> tblproducts.id, status di tblhosting.domainstatus.
            $rows = Capsule::table('tblhosting')
                ->whereIn('packageid', $ids)
                ->select('packageid', 'domainstatus', Capsule::raw('COUNT(*) AS total'))
                ->groupBy('packageid', 'domainstatus')
                ->get();

            foreach ($rows as $row) {
                $counts[(int) $row->packageid][(string) $row->domainstatus] = (int) $row->total;
            }
        }

        return ['total' => $total, 'products' => $products, 'counts' => $counts];
    }

    /**
     * Q3: satu product beserta group-nya.
     */
    public static function product(int $pid): ?object
    {
        return Capsule::table('tblproducts as p')
            ->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
            ->where('p.id', $pid)
            ->select('p.id', 'p.name', 'p.hidden', 'g.name as group_name')
            ->first();
    }

    /**
     * Q5: jumlah service per status untuk satu product. Hasil dipakai sebagai
     * whitelist pilihan status: status standar selalu ada (meski 0), ditambah
     * status lain yang benar-benar tercatat untuk product ini.
     *
     * @return array<string,int> status => jumlah
     */
    public static function statusCounts(int $pid): array
    {
        $counts = array_fill_keys(self::KNOWN_STATUSES, 0);

        $rows = Capsule::table('tblhosting')
            ->where('packageid', $pid)
            ->select('domainstatus', Capsule::raw('COUNT(*) AS total'))
            ->groupBy('domainstatus')
            ->get();

        foreach ($rows as $row) {
            $status = (string) $row->domainstatus;
            if ($status !== '') {
                $counts[$status] = (int) $row->total;
            }
        }

        return $counts;
    }

    /**
     * Q4: service untuk satu product, difilter status terpilih dan
     * opsional rentang tanggal terminate.
     */
    public static function serviceQuery(int $pid, array $filters)
    {
        $query = Capsule::table('tblhosting as h')
            ->leftJoin('tblclients as c', 'c.id', '=', 'h.userid')
            ->where('h.packageid', $pid);

        // Status sudah di-whitelist; jika semua dipilih, tidak perlu filter
        // (agar service dengan status kosong/tidak standar ikut tampil).
        if (!$filters['allStatuses']) {
            $query->whereIn('h.domainstatus', $filters['statuses']);
        }

        $columns = [
            'h.id', 'h.userid', 'h.domain', 'h.packageid', 'h.domainstatus',
            'c.firstname', 'c.lastname', 'c.companyname',
        ];

        $dateCol = $filters['dateCol'];
        if ($dateCol !== null) {
            // [VERIFIKASI] Tipe kolom date/datetime; batas akhir dibuat inklusif.
            // Service tanpa tanggal terminate otomatis tidak lolos filter ini.
            if ($filters['from'] !== null) {
                $query->where('h.' . $dateCol, '>=', $filters['from'] . ' 00:00:00');
            }
            if ($filters['to'] !== null) {
                $query->where('h.' . $dateCol, '<=', $filters['to'] . ' 23:59:59');
            }
            $columns[] = 'h.' . $dateCol . ' as terminated_at';
        }

        return $query->select($columns)->orderBy('h.id');
    }

    public static function servicePage(int $pid, array $filters, int $page, int $perPage): array
    {
        $query = self::serviceQuery($pid, $filters);

        return [
            'total' => (clone $query)->count('h.id'),
            'rows' => (clone $query)->forPage($page, $perPage)->get()->all(),
        ];
    }

    /**
     * Iterasi seluruh hasil Q4 per batch (keyset h.id > lastId) untuk export,
     * supaya memori tetap kecil pada data besar.
     */
    public static function eachService(int $pid, array $filters, callable $callback): void
    {
        $lastId = 0;
        do {
            $rows = self::serviceQuery($pid, $filters)
                ->where('h.id', '>', $lastId)
                ->limit(self::EXPORT_BATCH)
                ->get()
                ->all();

            foreach ($rows as $row) {
                $callback($row);
                $lastId = (int) $row->id;
            }
        } while (count($rows) === self::EXPORT_BATCH);
    }
}
