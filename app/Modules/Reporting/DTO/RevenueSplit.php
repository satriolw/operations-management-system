<?php

namespace App\Modules\Reporting\DTO;

/**
 * Split revenue Terealisasi (paid) vs Piutang (unpaid) — OPS-202, PRD §8 P0-7.
 * Invariant: realized + receivable == total_sales (total_sales NEVIRA mencakup piutang B2B).
 */
final class RevenueSplit
{
    public function __construct(
        public readonly int $totalSales,
        public readonly int $realized,   // terealisasi (paid) = total - piutang
        public readonly int $receivable, // piutang (unpaid) = Σ grand_total unpaid
    ) {}

    public function balances(): bool
    {
        return $this->realized + $this->receivable === $this->totalSales;
    }

    /** Token renderer (OPS-203/903). */
    public function toTokens(): array
    {
        return ['realized' => $this->realized, 'piutang' => $this->receivable];
    }
}
