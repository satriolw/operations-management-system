<?php

namespace App\Modules\Ingestion\DTO;

/**
 * Saldo deposit tingkat-merchant NEVIRA (OPS-1201, System Design §3.15). Hanya saldo_total +
 * breakdown (count per aksi) — TIDAK membawa history (1.989 halaman). Runway/burn diturunkan
 * dari delta antar-snapshot (OPS-1202).
 */
final class MerchantBalanceDTO
{
    public function __construct(
        public readonly int $saldoTotal,
        public readonly array $breakdown,
        public readonly array $raw,
    ) {}

    public static function fromResponse(array $raw): self
    {
        return new self(
            saldoTotal: (int) round((float) ($raw['saldo_total'] ?? 0)),
            breakdown: (array) ($raw['breakdown'] ?? []),
            raw: $raw,
        );
    }
}
