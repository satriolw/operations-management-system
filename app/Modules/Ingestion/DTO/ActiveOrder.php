<?php

namespace App\Modules\Ingestion\DTO;

use App\Support\Time\Wib;
use Carbon\CarbonImmutable;

/**
 * Order berjalan untuk SLA (OPS-1301, Epic M). Field SLA dari /transactions, null-safe; timestamp
 * tingkat-transaksi dinormalkan WIB (estimated_completion_date/updated_at) — BUKAN nested services
 * (UTC). Reuse activeOrders (OPS-1102); tanpa PII pelanggan.
 */
final class ActiveOrder
{
    public function __construct(
        public readonly ?string $transactionNumber,
        public readonly ?string $status,
        public readonly ?float $progressPercentage,
        public readonly ?CarbonImmutable $estimatedCompletion,
        public readonly ?CarbonImmutable $completionDate,
        public readonly ?CarbonImmutable $updatedAt,
        public readonly ?string $idRack,
        public readonly ?string $orderType,
        public readonly ?int $idCashier,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            transactionNumber: $row['transaction_number'] ?? null,
            status: $row['status'] ?? null,
            progressPercentage: isset($row['progress_percentage']) ? (float) $row['progress_percentage'] : null,
            estimatedCompletion: Wib::parseNullable($row['estimated_completion_date'] ?? null),
            completionDate: Wib::parseNullable($row['completion_date'] ?? null),
            updatedAt: Wib::parseNullable($row['updated_at'] ?? null),
            idRack: isset($row['id_rack']) ? (string) $row['id_rack'] : null,
            orderType: $row['order_type'] ?? null,
            idCashier: isset($row['id_cashier']) ? (int) $row['id_cashier'] : null,
        );
    }

    public function isOpen(): bool
    {
        return $this->completionDate === null;
    }
}
