<?php
/**
 * SATU-SATUNYA bagian modul yang menulis ke database: hapus product yang
 * tidak dirujuk service apa pun (semua status = 0), beserta data turunannya.
 *
 * Semua dijalankan dalam satu transaksi. Jumlah service dicek ulang di dalam
 * transaksi dengan lock, jadi product yang mendadak punya service tidak terhapus.
 *
 * [VERIFIKASI] Daftar tabel turunan di bawah mengikuti skema WHMCS 8.x yang
 * umum. Bandingkan dengan penghapusan bawaan WHMCS (configproducts.php) di
 * staging. Tabel/kolom yang tidak ada di skema Anda dilewati otomatis.
 * Referensi teks di tempat lain (mis. tblpromotions.appliesto) tidak diubah.
 */

namespace WHMCS\Module\Addon\ProductAudit;

use WHMCS\Database\Capsule;

final class ProductDeleter
{
    /** [tabel, kolom yang berisi product ID, kondisi tambahan] */
    private const RELATED = [
        ['tblpricing', 'relid', ['type' => 'product']],
        ['tblproductconfiglinks', 'pid', []],
        ['tblproduct_upgrade_products', 'product_id', []],
        ['tblproduct_upgrade_products', 'upgrade_product_id', []],
        ['tblproduct_downloads', 'product_id', []],
        ['tblproducts_slugs', 'product_id', []],
    ];

    /**
     * @return array{ok: bool, message: string}
     */
    public static function delete(int $pid): array
    {
        try {
            return Capsule::connection()->transaction(static function () use ($pid) {
                $schema = Capsule::schema();

                $product = Capsule::table('tblproducts')
                    ->where('id', $pid)
                    ->lockForUpdate()
                    ->first(['id', 'name']);
                if ($product === null) {
                    return ['ok' => false, 'message' => 'Product #' . $pid . ' tidak ditemukan atau sudah dihapus.'];
                }

                $services = Capsule::table('tblhosting')->where('packageid', $pid)->lockForUpdate()->count();
                if ($services > 0) {
                    return [
                        'ok' => false,
                        'message' => 'Product #' . $pid . ' masih dirujuk ' . $services . ' service, tidak dihapus.',
                    ];
                }

                $removed = [];

                // Custom field product (dan nilainya, bila masih ada).
                if ($schema->hasTable('tblcustomfields')) {
                    $fieldIds = Capsule::table('tblcustomfields')
                        ->where('type', 'product')
                        ->where('relid', $pid)
                        ->pluck('id')
                        ->all();
                    if ($fieldIds !== []) {
                        if ($schema->hasTable('tblcustomfieldsvalues')) {
                            $removed['tblcustomfieldsvalues'] = Capsule::table('tblcustomfieldsvalues')
                                ->whereIn('fieldid', $fieldIds)
                                ->delete();
                        }
                        $removed['tblcustomfields'] = Capsule::table('tblcustomfields')->whereIn('id', $fieldIds)->delete();
                    }
                }

                foreach (self::RELATED as [$table, $column, $extra]) {
                    if (!$schema->hasTable($table) || !$schema->hasColumn($table, $column)) {
                        continue;
                    }
                    $query = Capsule::table($table)->where($column, $pid);
                    foreach ($extra as $key => $value) {
                        $query->where($key, $value);
                    }
                    $count = $query->delete();
                    if ($count > 0) {
                        $removed[$table] = ($removed[$table] ?? 0) + $count;
                    }
                }

                Capsule::table('tblproducts')->where('id', $pid)->delete();

                $detail = [];
                foreach ($removed as $table => $count) {
                    if ($count > 0) {
                        $detail[] = $table . '=' . $count;
                    }
                }

                return [
                    'ok' => true,
                    'message' => 'Product #' . $pid . ' (' . $product->name . ') dihapus'
                        . ($detail !== [] ? '; data terkait: ' . implode(', ', $detail) : '') . '.',
                ];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menghapus product #' . $pid . '; tidak ada perubahan yang disimpan.'];
        }
    }
}
