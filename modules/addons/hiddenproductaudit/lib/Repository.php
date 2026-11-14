<?php
/**
 * Semua query modul. HANYA SELECT melalui WHMCS\Database\Capsule
 * (Illuminate query builder, parameter binding otomatis).
 */

namespace WHMCS\Module\Addon\HiddenProductAudit;

use WHMCS\Database\Capsule;

final class Repository
{
    /** Status yang punya kolom sendiri; status lain dijumlah ke "Lainnya". */
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
     * Q1 + Q2: product hidden (paged) dan jumlah service per status.
     */
    public static function hiddenProductPage(int $page, int $perPage): array
    {
        // [VERIFIKASI] tblproducts.hidden bernilai 1 untuk product hidden.
        $base = Capsule::table('tblproducts as p')->where('p.hidden', 1);

        $total = (clone $base)->count('p.id');

        $products = (clone $base)
            ->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
            ->select('p.id', 'p.name', 'g.name as group_name')
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
     * Q4: service Terminated untuk satu product, opsional filter tanggal terminate.
     */
    public static function terminatedQuery(int $pid, ?string $from, ?string $to, ?string $dateCol)
    {
        $query = Capsule::table('tblhosting as h')
            ->leftJoin('tblclients as c', 'c.id', '=', 'h.userid')
            ->where('h.packageid', $pid)
            ->where('h.domainstatus', 'Terminated');

        $columns = [
            'h.id', 'h.userid', 'h.domain', 'h.packageid', 'h.domainstatus',
            'c.firstname', 'c.lastname', 'c.companyname',
        ];

        if ($dateCol !== null) {
            // [VERIFIKASI] Tipe kolom date/datetime; batas akhir dibuat inklusif.
            if ($from !== null) {
                $query->where('h.' . $dateCol, '>=', $from . ' 00:00:00');
            }
            if ($to !== null) {
                $query->where('h.' . $dateCol, '<=', $to . ' 23:59:59');
            }
            $columns[] = 'h.' . $dateCol . ' as terminated_at';
        }

        return $query->select($columns)->orderBy('h.id');
    }

    public static function terminatedPage(int $pid, ?string $from, ?string $to, ?string $dateCol, int $page, int $perPage): array
    {
        $query = self::terminatedQuery($pid, $from, $to, $dateCol);

        return [
            'total' => (clone $query)->count('h.id'),
            'rows' => (clone $query)->forPage($page, $perPage)->get()->all(),
        ];
    }

    /**
     * Iterasi seluruh hasil Q4 per batch (keyset h.id > lastId) untuk export,
     * supaya memori tetap kecil pada data besar.
     */
    public static function eachTerminated(int $pid, ?string $from, ?string $to, ?string $dateCol, callable $callback): void
    {
        $lastId = 0;
        do {
            $rows = self::terminatedQuery($pid, $from, $to, $dateCol)
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
