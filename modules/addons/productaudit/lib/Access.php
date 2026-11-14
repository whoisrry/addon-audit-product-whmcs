<?php
/**
 * Pemeriksaan akses admin (fail closed).
 *
 * Lapisan tambahan di atas "Access Control" bawaan addon WHMCS: hanya role
 * yang terdaftar di field "Allowed Admin Role IDs" yang boleh membuka modul.
 *
 * [VERIFIKASI] Bergantung pada tbladmins (id, roleid, disabled) di WHMCS 8.x.
 */

namespace WHMCS\Module\Addon\ProductAudit;

use WHMCS\Database\Capsule;

final class Access
{
    public static function isAllowed(int $adminId, string $allowedRaw): bool
    {
        if ($adminId <= 0) {
            return false;
        }

        $roles = [];
        foreach (explode(',', $allowedRaw) as $part) {
            $part = trim($part);
            if (ctype_digit($part) && (int) $part > 0) {
                $roles[] = (int) $part;
            }
        }
        if ($roles === []) {
            return false;
        }

        // SELECT roleid, disabled FROM tbladmins WHERE id = ? LIMIT 1
        $admin = Capsule::table('tbladmins')
            ->where('id', $adminId)
            ->select('roleid', 'disabled')
            ->first();

        if ($admin === null || !empty($admin->disabled)) {
            return false;
        }

        return in_array((int) $admin->roleid, $roles, true);
    }
}
