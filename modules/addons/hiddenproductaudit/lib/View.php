<?php
/**
 * Render HTML admin. Semua nilai dinamis melewati View::e() (anti-XSS).
 * Markup memakai kelas Bootstrap 3 yang dipakai tema admin WHMCS 8.x.
 */

namespace WHMCS\Module\Addon\HiddenProductAudit;

final class View
{
    public const MODULE = 'hiddenproductaudit';

    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(array $params = []): string
    {
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '' && $v !== 0);

        return 'addonmodules.php?' . http_build_query(['module' => self::MODULE] + $params);
    }

    public static function alerts(array $messages): string
    {
        $html = '';
        foreach ($messages as $message) {
            $html .= '<div class="alert alert-warning">' . self::e($message) . '</div>';
        }

        return $html;
    }

    public static function clientName(object $row): string
    {
        $name = trim(($row->firstname ?? '') . ' ' . ($row->lastname ?? ''));
        $company = trim((string) ($row->companyname ?? ''));
        if ($company !== '') {
            $name = $name === '' ? $company : $name . ' (' . $company . ')';
        }

        return $name !== '' ? $name : '-';
    }

    /**
     * Cegah CSV/formula injection saat file dibuka di spreadsheet.
     */
    public static function csvCell($value): string
    {
        $text = (string) $value;
        if ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) {
            return "'" . $text;
        }

        return $text;
    }

    public static function summary(array $data, int $page, int $perPage): string
    {
        $html = '<h3>Ringkasan Product Hidden</h3>'
            . '<p>Total product hidden: <strong>' . (int) $data['total'] . '</strong></p>';

        if ($data['products'] === []) {
            return $html . '<div class="alert alert-info">Tidak ada product hidden untuk ditampilkan.</div>';
        }

        $html .= '<div class="table-responsive"><table class="table table-striped table-condensed">'
            . '<thead><tr><th>ID</th><th>Product</th><th>Group</th>';
        foreach (Repository::KNOWN_STATUSES as $status) {
            $html .= '<th class="text-right">' . self::e($status) . '</th>';
        }
        $html .= '<th class="text-right">Lainnya</th><th class="text-right">Total</th><th></th></tr></thead><tbody>';

        foreach ($data['products'] as $product) {
            $counts = $data['counts'][(int) $product->id] ?? [];
            $known = 0;
            $cells = '';
            foreach (Repository::KNOWN_STATUSES as $status) {
                $n = (int) ($counts[$status] ?? 0);
                $known += $n;
                $cells .= '<td class="text-right">' . $n . '</td>';
            }
            $total = array_sum($counts);

            $html .= '<tr>'
                . '<td>' . (int) $product->id . '</td>'
                . '<td>' . self::e($product->name) . '</td>'
                . '<td>' . self::e($product->group_name ?? '-') . '</td>'
                . $cells
                . '<td class="text-right">' . ($total - $known) . '</td>'
                . '<td class="text-right"><strong>' . $total . '</strong></td>'
                . '<td><a class="btn btn-default btn-xs" href="'
                . self::e(self::url(['action' => 'detail', 'pid' => (int) $product->id])) . '">Detail</a></td>'
                . '</tr>';
        }

        return $html . '</tbody></table></div>'
            . self::pager('summary', [], $page, $perPage, (int) $data['total']);
    }

    public static function detail(array $ctx): string
    {
        $product = $ctx['product'];
        $pid = (int) $product->id;
        $label = '#' . $pid . ' ' . $product->name;
        $filters = ['pid' => $pid, 'from' => $ctx['from'], 'to' => $ctx['to']];
        $disabled = $ctx['dateCol'] === null ? ' disabled' : '';

        $html = '<p><a href="' . self::e(self::url()) . '">&laquo; Kembali ke ringkasan</a></p>'
            . '<h3>Service Terminated &mdash; ' . self::e($label) . '</h3>'
            . '<p>Group: ' . self::e($product->group_name ?? '-')
            . ' &middot; Jumlah service: <strong>' . (int) $ctx['total'] . '</strong></p>';

        $html .= '<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:15px">'
            . '<input type="hidden" name="module" value="' . self::MODULE . '">'
            . '<input type="hidden" name="action" value="detail">'
            . '<input type="hidden" name="pid" value="' . $pid . '">'
            . '<label>Terminate dari&nbsp;<input type="date" class="form-control input-sm" name="from" value="'
            . self::e($ctx['from'] ?? '') . '"' . $disabled . '></label> '
            . '<label>sampai&nbsp;<input type="date" class="form-control input-sm" name="to" value="'
            . self::e($ctx['to'] ?? '') . '"' . $disabled . '></label> '
            . '<button type="submit" class="btn btn-primary btn-sm">Terapkan</button> '
            . '<a class="btn btn-default btn-sm" href="' . self::e(self::url(['action' => 'detail', 'pid' => $pid])) . '">Reset</a> '
            . '<a class="btn btn-success btn-sm" href="' . self::e(self::url(['action' => 'export'] + $filters)) . '">Export CSV</a>'
            . '</form>';

        $html .= '<div class="table-responsive"><table class="table table-striped table-condensed"><thead><tr>'
            . '<th>Service ID</th><th>Client ID</th><th>Nama Client</th><th>Domain / Service</th>'
            . '<th>Product</th><th>Status</th><th>Tanggal Terminate</th></tr></thead><tbody>';

        if ($ctx['rows'] === []) {
            $html .= '<tr><td colspan="7" class="text-center">Tidak ada service Terminated untuk filter ini.</td></tr>';
        }

        foreach ($ctx['rows'] as $row) {
            $domain = (string) ($row->domain ?? '');
            $html .= '<tr>'
                . '<td>' . (int) $row->id . '</td>'
                . '<td>' . (int) $row->userid . '</td>'
                . '<td>' . self::e(self::clientName($row)) . '</td>'
                . '<td>' . self::e($domain !== '' ? $domain : '-') . '</td>'
                . '<td>' . self::e($label) . '</td>'
                . '<td>' . self::e($row->domainstatus) . '</td>'
                . '<td>' . self::e($row->terminated_at ?? '-') . '</td>'
                . '</tr>';
        }

        return $html . '</tbody></table></div>'
            . self::pager('detail', $filters, (int) $ctx['page'], (int) $ctx['perPage'], (int) $ctx['total']);
    }

    private static function pager(string $action, array $params, int $page, int $perPage, int $total): string
    {
        $pages = max(1, (int) ceil($total / $perPage));
        if ($pages <= 1) {
            return '';
        }

        $link = static function (int $target, string $text) use ($action, $params): string {
            return '<li><a href="' . self::e(self::url(['action' => $action, 'page' => $target] + $params)) . '">'
                . $text . '</a></li>';
        };

        $html = '<div class="text-center"><ul class="pagination">';
        $html .= $page > 1 ? $link(1, '&laquo;') . $link($page - 1, '&lsaquo; Sebelumnya')
            : '<li class="disabled"><span>&laquo;</span></li>';
        $html .= '<li class="active"><span>Halaman ' . $page . ' / ' . $pages . '</span></li>';
        $html .= $page < $pages ? $link($page + 1, 'Berikutnya &rsaquo;') . $link($pages, '&raquo;')
            : '<li class="disabled"><span>&raquo;</span></li>';

        return $html . '</ul></div>';
    }
}
