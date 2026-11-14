<?php
/**
 * Validasi input $_GET. Semua nilai di-whitelist atau divalidasi tipe/format;
 * nilai yang tidak valid diganti default, bukan diteruskan ke query.
 */

namespace WHMCS\Module\Addon\ProductAudit;

final class Input
{
    private const ACTIONS = ['summary', 'detail', 'export'];

    public static function action(): string
    {
        $action = $_GET['action'] ?? 'summary';

        return is_string($action) && in_array($action, self::ACTIONS, true) ? $action : 'summary';
    }

    public static function int(string $key, int $default, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]);

        return $value === false ? $default : $value;
    }

    /** Checkbox: true hanya jika nilainya persis "1". */
    public static function flag(string $key): bool
    {
        return ($_GET[$key] ?? null) === '1';
    }

    /**
     * Daftar status terpilih, hanya yang ada di whitelist $allowed
     * (urutan mengikuti $allowed, duplikat dibuang).
     *
     * @param string[] $allowed
     * @return string[]
     */
    public static function statuses(string $key, array $allowed): array
    {
        $raw = $_GET[$key] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $raw = array_filter($raw, 'is_string');

        return array_values(array_filter($allowed, static fn ($s) => in_array($s, $raw, true)));
    }

    /**
     * Tanggal format Y-m-d yang benar-benar valid (mis. 2024-02-30 ditolak).
     */
    public static function date(string $key): ?string
    {
        $raw = $_GET[$key] ?? '';
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return ($date !== false && $date->format('Y-m-d') === $raw) ? $raw : null;
    }
}
