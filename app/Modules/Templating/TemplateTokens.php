<?php

namespace App\Modules\Templating;

/**
 * Katalog token template (OPS-901, System Design §3.9). Token mengikat field turunan
 * response NEVIRA. Token di luar katalog ditolak (validasi builder/seed).
 */
final class TemplateTokens
{
    /** @var string[] */
    public const ALLOWED = [
        'nama_outlet', 'nama_investor', 'tanggal',
        'total_sales', 'realized', 'piutang', 'txn_count',
        'avg_transaction', 'avg_customer_spending',
        'volume_kg', 'volume_pcs',
        'penyesuaian_revenue',
    ];

    /** Token bernilai Rupiah (format id-ID). */
    public const RUPIAH = ['total_sales', 'realized', 'piutang', 'avg_transaction', 'avg_customer_spending'];

    /**
     * Ekstrak semua token {{...}} dari layout_json.
     *
     * @param  array<int,array<string,mixed>>  $layout
     * @return string[] daftar token unik
     */
    public static function extract(array $layout): array
    {
        $json = json_encode($layout, JSON_UNESCAPED_UNICODE);
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', (string) $json, $m);

        // token eksplisit pada field "token" + token inline di teks
        $explicit = collect($layout)->pluck('token')->filter()->all();

        return array_values(array_unique([...$m[1], ...$explicit]));
    }

    /**
     * Token yang TIDAK dikenal pada layout (kosong = valid).
     *
     * @param  array<int,array<string,mixed>>  $layout
     * @return string[]
     */
    public static function invalidTokens(array $layout): array
    {
        return array_values(array_diff(self::extract($layout), self::ALLOWED));
    }

    public static function isValid(array $layout): bool
    {
        return self::invalidTokens($layout) === [];
    }

    public static function isRupiah(string $token): bool
    {
        return in_array($token, self::RUPIAH, true);
    }
}
