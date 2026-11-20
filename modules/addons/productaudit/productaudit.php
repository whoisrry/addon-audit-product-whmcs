<?php
/**
 * Product Audit - addon module WHMCS.
 *
 * Menampilkan ringkasan product (opsional hanya yang hidden) beserta jumlah
 * service per status, dan daftar service per product dengan filter status.
 *
 * Satu-satunya operasi tulis adalah hapus product yang tidak punya service
 * sama sekali (lib/ProductDeleter.php), setelah konfirmasi password admin dan
 * konfirmasi Ya/Tidak. Activate/deactivate tidak mengubah skema.
 *
 * Target: WHMCS 8.13.7 (kompatibel 8.x). Bagian yang bergantung versi
 * ditandai [VERSI], query yang perlu dicek di skema Anda ditandai [VERIFIKASI].
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/Input.php';
require_once __DIR__ . '/lib/Repository.php';
require_once __DIR__ . '/lib/View.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/ProductDeleter.php';

use WHMCS\Module\Addon\ProductAudit\Auth;
use WHMCS\Module\Addon\ProductAudit\Input;
use WHMCS\Module\Addon\ProductAudit\ProductDeleter;
use WHMCS\Module\Addon\ProductAudit\Repository;
use WHMCS\Module\Addon\ProductAudit\View;

function productaudit_config()
{
    return [
        'name' => 'Product Audit',
        'description' => 'Audit product dan service yang merujuknya per status, serta hapus product tanpa service.',
        'author' => 'Internal',
        'language' => 'english',
        'version' => '1.4.0',
        // Akses admin diatur lewat checklist "Access Control" bawaan WHMCS
        // (System Settings > Addon Modules > Configure). WHMCS hanya memanggil
        // productaudit_output() untuk role yang dicentang di sana.
        'fields' => [
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
    // Pembatasan akses dilakukan WHMCS sebelum fungsi ini dipanggil
    // (login admin + role tercentang di Access Control modul).
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

    // Filter ringkasan juga dibawa ke halaman detail agar tombol "Kembali"
    // mengembalikan admin ke hasil pencarian yang sama.
    $groups = Repository::groups();
    $search = productaudit_search_filters($groups);
    $back = View::searchParams($search);

    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $postAction = $isPost ? Input::postString('pa_action', 20) : '';

    // 1) Konfirmasi password admin, seperti halaman sensitif WHMCS (configgeneral.php).
    //    Halaman yang diminta (kecuali export) dipertahankan sebagai tujuan form.
    $returnUrl = View::url(
        ($action === 'export' ? [] : ['action' => $action === 'summary' ? null : $action, 'pid' => $pid]) + $back
    );
    if ($postAction === 'auth') {
        $password = $_POST['pa_password'] ?? '';
        unset($_POST['pa_password']);
        $error = !Auth::checkCsrf($_POST['pa_csrf'] ?? null)
            ? 'Formulir kedaluwarsa, silakan coba lagi.'
            : Auth::confirm(is_string($password) ? $password : '');
        $password = null;
        if ($error !== null) {
            echo View::passwordForm($returnUrl, $error);
            return;
        }
    }
    if (!Auth::isConfirmed()) {
        echo View::passwordForm($returnUrl, null);
        return;
    }

    // 2) Proses hapus (POST dari halaman konfirmasi Ya/Tidak).
    $notices = [];
    $errors = [];
    if ($postAction === 'delete') {
        $deletePid = Input::postInt('pid');
        if (!Auth::checkCsrf($_POST['pa_csrf'] ?? null)) {
            $errors[] = 'Formulir kedaluwarsa, product tidak dihapus. Silakan ulangi.';
        } elseif ($deletePid <= 0) {
            $errors[] = 'Product ID tidak valid.';
        } else {
            $result = ProductDeleter::delete($deletePid);
            if ($result['ok']) {
                $notices[] = $result['message'];
                Auth::log('Product Audit: ' . $result['message'] . ' (admin ID ' . Auth::adminId() . ')');
            } else {
                $errors[] = $result['message'];
            }
        }
        $action = 'summary';
    }

    if ($action === 'summary') {
        $data = Repository::productPage($search, $page, $perPage);
        echo View::alerts($notices, 'success') . View::alerts($errors, 'danger')
            . View::summary($data, $search, $groups, $page, $perPage);
        return;
    }

    // detail, export & delete: butuh product yang valid.
    $product = $pid > 0 ? Repository::product($pid) : null;
    if ($product === null) {
        echo View::alerts([$pid > 0 ? 'Product #' . $pid . ' tidak ditemukan.' : 'Product ID tidak valid.'])
            . '<p><a href="' . View::e(View::url($back)) . '">&laquo; Kembali ke ringkasan</a></p>';
        return;
    }

    if ($action === 'delete') {
        // Halaman konfirmasi Ya/Tidak (tanpa JavaScript). Penghapusan baru
        // terjadi lewat POST dari form di halaman ini.
        echo View::deleteConfirm($product, Repository::serviceCount($pid), $search);
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
        'search' => $search,
    ]);
}

/**
 * Filter halaman ringkasan: kata kunci, group, dan is Hidden.
 *
 * @param array<int,string> $groups gid => nama group (whitelist gid)
 * @return array{q: string, gid: int, hidden: bool}
 */
function productaudit_search_filters(array $groups): array
{
    $gid = Input::int('gid', 0, 1);

    return [
        'q' => Input::text('q', 100),
        'gid' => isset($groups[$gid]) ? $gid : 0,
        'hidden' => Input::flag('hidden'),
    ];
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
