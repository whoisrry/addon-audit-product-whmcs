<?php
/**
 * Konfirmasi password admin sebelum modul bisa dipakai, mirip halaman
 * sensitif WHMCS (mis. configgeneral.php), plus token CSRF untuk form POST.
 *
 * - Konfirmasi berlaku 15 menit sejak aktivitas terakhir (sliding), terikat
 *   ke admin ID yang login.
 * - 5 kali salah password -> dikunci 5 menit (per sesi), dicatat di Activity Log.
 * - Password tidak pernah disimpan, dicatat, atau ditampilkan.
 *
 * [VERIFIKASI] Password admin dicocokkan dengan hash di tbladmins
 * (kolom `passwordhash` dan/atau `password`) memakai password_verify().
 * Admin yang login hanya via SSO/OAuth tanpa password lokal tidak bisa lolos.
 */

namespace WHMCS\Module\Addon\ProductAudit;

use WHMCS\Database\Capsule;

final class Auth
{
    private const SESSION_KEY = 'productaudit';
    private const IDLE_TIMEOUT = 900;
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    /** [VERSI] WHMCS 8.x menyimpan ID admin yang login di $_SESSION['adminid']. */
    public static function adminId(): int
    {
        return isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    }

    public static function isConfirmed(): bool
    {
        $state = &self::state();
        $adminId = self::adminId();
        $ok = $adminId > 0
            && ($state['admin'] ?? 0) === $adminId
            && ($state['until'] ?? 0) >= time();

        if ($ok) {
            $state['until'] = time() + self::IDLE_TIMEOUT;
        } else {
            unset($state['admin'], $state['until']);
        }

        return $ok;
    }

    /**
     * @return string|null pesan error, atau null jika password benar
     */
    public static function confirm(string $password): ?string
    {
        $state = &self::state();
        $lockedFor = ($state['locked_until'] ?? 0) - time();
        if ($lockedFor > 0) {
            return 'Terlalu banyak percobaan salah. Coba lagi dalam ' . (int) ceil($lockedFor / 60) . ' menit.';
        }

        $adminId = self::adminId();
        if ($adminId > 0 && $password !== '' && self::passwordMatches($adminId, $password)) {
            $state['admin'] = $adminId;
            $state['until'] = time() + self::IDLE_TIMEOUT;
            unset($state['fails'], $state['locked_until']);

            return null;
        }

        $state['fails'] = ($state['fails'] ?? 0) + 1;
        self::log('Product Audit: konfirmasi password gagal (admin ID ' . $adminId . ')');
        if ($state['fails'] >= self::MAX_ATTEMPTS) {
            $state['locked_until'] = time() + self::LOCK_SECONDS;
            unset($state['fails']);

            return 'Password salah. Terlalu banyak percobaan, dikunci ' . (self::LOCK_SECONDS / 60) . ' menit.';
        }

        return 'Password salah.';
    }

    public static function csrfToken(): string
    {
        $state = &self::state();
        if (empty($state['csrf']) || !is_string($state['csrf'])) {
            $state['csrf'] = bin2hex(random_bytes(32));
        }

        return $state['csrf'];
    }

    public static function checkCsrf($token): bool
    {
        $state = &self::state();

        return is_string($token) && !empty($state['csrf']) && is_string($state['csrf'])
            && hash_equals($state['csrf'], $token);
    }

    /** Hidden input CSRF modul + token bawaan WHMCS bila tersedia. */
    public static function csrfFields(): string
    {
        $html = '<input type="hidden" name="pa_csrf" value="' . View::e(self::csrfToken()) . '">';
        // [VERSI] Token CSRF bawaan admin area WHMCS.
        if (function_exists('generate_token')) {
            $html .= '<input type="hidden" name="token" value="' . View::e(generate_token('plain')) . '">';
        }

        return $html;
    }

    /** Catat ke Activity Log WHMCS (Utilities > Logs > Activity Log). */
    public static function log(string $message): void
    {
        if (function_exists('logActivity')) {
            logActivity($message);
        }
    }

    private static function passwordMatches(int $adminId, string $password): bool
    {
        $schema = Capsule::schema();
        $columns = array_values(array_filter(
            ['passwordhash', 'password'],
            static fn ($c) => $schema->hasColumn('tbladmins', $c)
        ));
        if ($columns === []) {
            return false;
        }

        $admin = Capsule::table('tbladmins')
            ->where('id', $adminId)
            ->select(array_merge($columns, ['disabled']))
            ->first();
        if ($admin === null || !empty($admin->disabled)) {
            return false;
        }

        foreach ($columns as $column) {
            $hash = (string) ($admin->{$column} ?? '');
            // Hanya hash modern (bcrypt/argon); hash lama/kosong otomatis gagal.
            if ($hash !== '' && password_verify($password, $hash)) {
                return true;
            }
        }

        return false;
    }

    private static function &state(): array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        return $_SESSION[self::SESSION_KEY];
    }
}
