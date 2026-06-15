<?php

namespace App\Support\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Normalisasi waktu kanonik ke Asia/Jakarta (WIB) — aturan emas #4 + Risiko R1.
 *
 * ⚠️ Jebakan nyata response NEVIRA:
 *  - Timestamp TINGKAT-TRANSAKSI (created_at, approve_refund_void_date) = WIB lokal
 *    tanpa offset, mis. "2026-06-12 13:10:12". → pakai {@see Wib::parse()}.
 *  - Timestamp nested di "services" = UTC dengan 'Z', mis. "2026-06-12T06:10:12.000000Z"
 *    (beda 7 jam). → JANGAN pakai untuk logika tanggal. Bila perlu, {@see Wib::fromUtc()}.
 *
 * SEMUA logika tanggal (Penyesuaian Revenue, cek diam) memakai field tingkat-transaksi.
 */
final class Wib
{
    public const TZ = 'Asia/Jakarta';

    /**
     * Parse string WIB tingkat-transaksi (TANPA offset) sebagai Asia/Jakarta.
     * Jangan berikan timestamp nested "services" (ber-'Z') ke sini.
     */
    public static function parse(string $localWib): CarbonImmutable
    {
        return CarbonImmutable::parse($localWib, self::TZ);
    }

    public static function parseNullable(?string $localWib): ?CarbonImmutable
    {
        return ($localWib === null || trim($localWib) === '') ? null : self::parse($localWib);
    }

    /**
     * Konversi eksplisit timestamp UTC (mis. nested "services" ber-'Z') ke WIB.
     * Disediakan agar konversi UTC→WIB tidak pernah implisit/tercampur.
     */
    public static function fromUtc(string $utc): CarbonImmutable
    {
        return CarbonImmutable::parse($utc, 'UTC')->setTimezone(self::TZ);
    }

    /** Normalkan Carbon apa pun ke WIB. */
    public static function normalize(CarbonInterface $dt): CarbonImmutable
    {
        return CarbonImmutable::instance($dt)->setTimezone(self::TZ);
    }
}
