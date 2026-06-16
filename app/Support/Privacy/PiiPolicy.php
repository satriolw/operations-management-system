<?php

namespace App\Support\Privacy;

/**
 * Kebijakan minim-PII (OPS-705, aturan emas #3).
 *
 * Output DB TIDAK boleh menyimpan PII customer (nama, telepon, alamat, email) dari record
 * void/refund. Yang boleh hanya metadata sinyal: transaction_number, nominal, alasan,
 * id_cashier, tanggal.
 */
final class PiiPolicy
{
    /**
     * Tabel framework/identitas OMS — di luar pemindaian PII customer.
     * (users.email = login staf OMS, bukan PII customer.)
     */
    public const NON_OUTPUT_TABLES = [
        'migrations', 'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    ];

    /**
     * Substring nama kolom yang menandakan PII customer (dilarang di tabel output).
     * Sengaja TIDAK memasukkan 'name' polos (mis. outlet name sah).
     */
    public const FORBIDDEN_COLUMN_TOKENS = [
        'customer', 'phone', 'telepon', 'telephone',
        'alamat', 'address', 'no_hp', 'email',
    ];

    /**
     * Field yang BOLEH dipersist pada payload sinyal (whitelist). Selain ini dibuang
     * saat serialisasi agar PII customer tak pernah ikut tersimpan.
     */
    public const ALLOWED_SIGNAL_FIELDS = [
        'transaction_number', 'ref_transaction_number',
        'amount', 'nominal', 'grand_total',
        'reason', 'id_cashier', 'id_outlet',
        'date', 'nota_date', 'approved_at', 'restated_for_date',
        'type', 'severity', 'progress_percentage', 'payment_status',
    ];

    /**
     * Pengecualian sah per-tabel: kolom infra bisnis yang kebetulan cocok token, BUKAN PII customer.
     * mis. nomor pengirim WhatsApp = data konfigurasi, bukan telepon customer.
     *
     * @var array<string, string[]>
     */
    public const ALLOWED_COLUMNS = [
        'whatsapp_accounts' => ['phone_number'],
    ];

    public static function isForbiddenColumn(string $column, ?string $table = null): bool
    {
        if ($table !== null && in_array($column, self::ALLOWED_COLUMNS[$table] ?? [], true)) {
            return false; // infra bisnis, bukan PII customer
        }

        $c = strtolower($column);
        foreach (self::FORBIDDEN_COLUMN_TOKENS as $token) {
            if (str_contains($c, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Saring payload sinyal ke field yang diizinkan saja (buang PII customer).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrubSignalPayload(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::ALLOWED_SIGNAL_FIELDS));
    }
}
