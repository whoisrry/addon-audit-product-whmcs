<?php
/**
 * Hidden Product Audit - addon module WHMCS (READ-ONLY).
 *
 * Menampilkan ringkasan product yang di-hidden dan daftar service Terminated
 * yang masih merujuk product tersebut. Modul ini TIDAK menulis ke database:
 * tidak ada INSERT/UPDATE/DELETE/DDL, termasuk saat activate/deactivate.
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

use WHMCS\Module\Addon\HiddenProductAudit\Access;
use WHMCS\Module\Addon\HiddenProductAudit\Input;
use WHMCS\Module\Addon\HiddenProductAudit\Repository;
use WHMCS\Module\Addon\HiddenProductAudit\View;

function hiddenproductaudit_config()
{
    return [
        'name' => 'Hidden Product Audit',
        'description' => 'Audit read-only product hidden dan service Terminated yang masih merujuknya.',
        'author' => 'Internal',
        'language' => 'english',
        'version' => '1.0.0',
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
function hiddenproductaudit_activate()
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
function hiddenproductaudit_deactivate()
{
    return ['status' => 'success', 'description' => 'Modul dinonaktifkan. Tidak ada data yang dihapus.'];
}

function hiddenproductaudit_output($vars)
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
    $from = Input::date('from');
    $to = Input::date('to');

    $warnings = [];
    if ($from !== null && $to !== null && $from > $to) {
        $warnings[] = 'Tanggal "dari" lebih besar dari "sampai"; filter tanggal diabaikan.';
        $from = $to = null;
    }

    $dateCol = Repository::terminateColumn();
    if ($dateCol === null) {
        $warnings[] = 'Kolom tanggal terminate tidak ditemukan di tblhosting; filter tanggal dinonaktifkan. Periksa skema versi Anda.';
        $from = $to = null;
    }

    if ($action === 'export') {
        hiddenproductaudit_export($pid, $from, $to, $dateCol);
        return;
    }

    if ($action === 'detail' && $pid > 0) {
        $product = Repository::product($pid);
        if ($product === null) {
            echo View::alerts(['Product #' . $pid . ' tidak ditemukan.']);
            return;
        }
        if (empty($product->hidden)) {
            $warnings[] = 'Product #' . $pid . ' saat ini tidak berstatus hidden.';
        }

        $result = Repository::terminatedPage($pid, $from, $to, $dateCol, $page, $perPage);
        echo View::alerts($warnings) . View::detail([
            'product' => $product,
            'from' => $from,
            'to' => $to,
            'dateCol' => $dateCol,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
        ]);
        return;
    }

    $data = Repository::hiddenProductPage($page, $perPage);
    echo View::alerts($warnings) . View::summary($data, $page, $perPage);
}

/**
 * Export CSV seluruh hasil filter (semua halaman) untuk satu product.
 */
function hiddenproductaudit_export($pid, $from, $to, $dateCol)
{
    $product = $pid > 0 ? Repository::product($pid) : null;
    if ($product === null) {
        echo View::alerts(['Export membutuhkan product ID yang valid.']);
        return;
    }

    // [VERSI] Buang output buffer template admin WHMCS agar file CSV bersih.
    // Uji di staging: perilaku buffering admin area bisa berbeda antar versi.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $filename = 'hidden-product-' . (int) $pid . '-terminated-' . date('Ymd-His') . '.csv';
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

    Repository::eachTerminated($pid, $from, $to, $dateCol, function ($row) use ($out, $product) {
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
