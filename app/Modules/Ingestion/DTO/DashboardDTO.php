<?php

namespace App\Modules\Ingestion\DTO;

use Carbon\CarbonInterface;

/**
 * Pembungkus tipis response dashboard harian NEVIRA. OPS-102 hanya menyediakan
 * akses mentah + identitas (outlet, tanggal); pemetaan field detail = OPS-103/201.
 */
final class DashboardDTO
{
    public function __construct(
        public readonly int $outletId,
        public readonly string $date,        // Y-m-d (WIB)
        public readonly array $raw,           // payload response apa adanya
    ) {}

    public static function fromResponse(int $outletId, CarbonInterface|string $date, array $raw): self
    {
        $d = $date instanceof CarbonInterface ? $date->format('Y-m-d') : (string) $date;

        return new self($outletId, $d, $raw);
    }

    /** Ambil field dari payload dengan dot-notation; pemetaan resmi menyusul di OPS-103. */
    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->raw, $key, $default);
    }
}
