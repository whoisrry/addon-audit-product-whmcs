<?php
/**
 * Product Audit - addon module WHMCS (READ-ONLY).
 *
 * Menampilkan ringkasan product (opsional hanya yang hidden) beserta jumlah
 * service per status, dan daftar service per product dengan filter status.
 * Modul ini TIDAK menulis ke database: tidak ada INSERT/UPDATE/DELETE/DDL,
 * termasuk saat activate/deactivate.
 *
 * Target: WHMCS 8.13.7 (kompatibel 8.x). Bagian yang bergantung versi
 * ditandai [VERSI], query yang perlu dicek di skema Anda ditandai [VERIFIKASI].
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Access.php';
require_once __DIR__ . '/lib/Input.php';
require_once __DIR__ . '/lib/Repository.php';
require_once __DIR__ . '/lib/View.php';

use WHMCS\Module\Addon\ProductAudit\Access;
use WHMCS\Module\Addon\ProductAudit\Input;
use WHMCS\Module\Addon\ProductAudit\Repository;
use WHMCS\Module\Addon\ProductAudit\View;

function productaudit_config()
{
    return [
        'name' => 'Product Audit',
        'description' => 'Audit read-only product dan service yang merujuknya, per status.',
        'author' => 'Internal',
        'language' => 'english',
        'version' => '1.1.0',
        'fields' => [
            'allowed_role_ids' => [
                'FriendlyName' => 'Allowed Admin Role IDs',
                'Type' => 'text',
                'Size' => '40',
                'Default' => '',
                'Description' => 'ID admin role yang boleh membuka modul, dipisah koma (contoh: 1,3). Kosong = semua ditolak.',
            ],
            'page_size' => [
                'FriendlyName' => 'Rows per Page',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '50',
                'Description' => 'Jumlah baris per halaman (10-200).',
            ],
        ],
    ];
}

/**
 * Activate hanya memeriksa keberadaan tabel. Tidak membuat/mengubah skema.
 */
function productaudit_activate()
{
    try {
        $missing = Repository::missingTables();
        if ($missing !== []) {
            return [
                'status' => 'error',
                'description' => 'Tabel tidak ditemukan: ' . implode(', ', $missing),
            ];
        }

        $note = Repository::terminateColumn() === null
            ? ' Peringatan: kolom tanggal terminate tidak ditemukan, filter tanggal akan dinonaktifkan.'
            : '';

        return [
            'status' => 'success',
            'description' => 'Modul aktif. Tidak ada tabel yang dibuat atau diubah.' . $note,
        ];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Gagal memeriksa skema database.'];
    }
}

/**
 * Deactivate tidak menghapus apa pun karena modul tidak pernah membuat data.
 */
function productaudit_deactivate()
{
    return ['status' => 'success', 'description' => 'Modul dinonaktifkan. Tidak ada data yang dihapus.'];
}

function productaudit_output($vars)
{
    // [VERSI] WHMCS 8.x menyimpan ID admin yang login di $_SESSION['adminid'].
    $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    if (!Access::isAllowed($adminId, (string) ($vars['allowed_role_ids'] ?? ''))) {
        echo '<div class="alert alert-danger">Akses ditolak. Role admin Anda tidak terdaftar di konfigurasi modul ini.</div>';
        return;
    }

    $missing = Repository::missingTables();
    if ($missing !== []) {
        echo '<div class="alert alert-danger">Tabel WHMCS tidak ditemukan: ' . View::e(implode(', ', $missing)) . '</div>';
        return;
    }

    $perPage = filter_var($vars['page_size'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 10, 'max_range' => 200, 'default' => 50],
    ]);

    $action = Input::action();
    $page = Input::int('page', 1, 1);
    $pid = Input::int('pid', 0, 1);

    if ($action === 'summary') {
        $hiddenOnly = Input::flag('hidden');
        $data = Repository::productPage($hiddenOnly, $page, $perPage);
        echo View::summary($data, $hiddenOnly, $page, $perPage);
        return;
    }

    // detail & export: butuh product yang valid.
    $product = $pid > 0 ? Repository::product($pid) : null;
    if ($product === null) {
        echo View::alerts([$pid > 0 ? 'Product #' . $pid . ' tidak ditemukan.' : 'Product ID tidak valid.'])
            . '<p><a href="' . View::e(View::url()) . '">&laquo; Kembali ke ringkasan</a></p>';
        return;
    }

    $warnings = [];
    $filters = productaudit_filters($pid, $warnings);

    if ($action === 'export') {
        productaudit_export($product, $filters);
        return;
    }

    $result = Repository::servicePage($pid, $filters, $page, $perPage);
    echo View::alerts($warnings) . View::detail([
        'product' => $product,
        'filters' => $filters,
        'rows' => $result['rows'],
        'total' => $result['total'],
        'page' => $page,
        'perPage' => $perPage,
    ]);
}

/**
 * Bangun filter detail/export dari $_GET yang sudah divalidasi.
 *
 * @return array{statuses: string[], statusOptions: array<string,int>, allStatuses: bool, from: ?string, to: ?string, dateCol: ?string}
 */
function productaudit_filters(int $pid, array &$warnings): array
{
    // Opsi status = status standar + status lain yang benar-benar ada di product ini.
    $statusOptions = Repository::statusCounts($pid);
    $statuses = Input::statuses('status', array_keys($statusOptions));
    $allStatuses = $statuses === [] || count($statuses) === count($statusOptions);
    if ($statuses === []) {
        if (Input::flag('sf')) {
            $warnings[] = 'Tidak ada status yang dipilih; menampilkan semua status.';
        }
        $statuses = array_keys($statusOptions);
    }

    $from = Input::date('from');
    $to = Input::date('to');
    if ($from !== null && $to !== null && $from > $to) {
        $warnings[] = 'Tanggal "dari" lebih besar dari "sampai"; filter tanggal diabaikan.';
        $from = $to = null;
    }

    $dateCol = Repository::terminateColumn();
    if ($dateCol === null) {
        $warnings[] = 'Kolom tanggal terminate tidak ditemukan di tblhosting; filter tanggal dinonaktifkan. Periksa skema versi Anda.';
        $from = $to = null;
    }

    return [
        'statuses' => $statuses,
        'statusOptions' => $statusOptions,
        'allStatuses' => $allStatuses,
        'from' => $from,
        'to' => $to,
        'dateCol' => $dateCol,
    ];
}

/**
 * Export CSV seluruh hasil filter (semua halaman) untuk satu product.
 */
function productaudit_export(object $product, array $filters)
{
    // [VERSI] Buang output buffer template admin WHMCS agar file CSV bersih.
    // Uji di staging: perilaku buffering admin area bisa berbeda antar versi.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'product-' . (int) $product->id . '-services-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8.
    fputcsv($out, [
        'service_id', 'client_id', 'client_name', 'domain_or_service',
        'product_id', 'product_name', 'status', 'terminated_at',
    ]);

    Repository::eachService((int) $product->id, $filters, function ($row) use ($out, $product) {
        fputcsv($out, array_map([View::class, 'csvCell'], [
            $row->id,
            $row->userid,
            View::clientName($row),
            $row->domain ?? '',
            $product->id,
            $product->name,
            $row->domainstatus,
            $row->terminated_at ?? '',
        ]));
    });

    fclose($out);
    exit;
}
