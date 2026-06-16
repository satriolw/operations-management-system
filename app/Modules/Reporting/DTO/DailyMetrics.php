<?php

namespace App\Modules\Reporting\DTO;

use App\Modules\Ingestion\DTO\DashboardDTO;

/**
 * Metrik harian terstruktur hasil agregasi dashboard NEVIRA (OPS-201). Output turunan
 * (bukan salinan transaksi). txn_count diturunkan konsisten bila tak tersedia langsung.
 */
final class DailyMetrics
{
    /**
     * @param  array<string,int>  $volumes  unit_volumes (mis. kg/pcs/m2), nilai 0 disembunyikan saat render
     */
    public function __construct(
        public readonly int $outletId,
        public readonly string $date,
        public readonly int $totalSales,
        public readonly int $avgTransaction,
        public readonly int $avgCustomerSpending,
        public readonly int $txnCount,
        public readonly array $volumes = [],
    ) {}

    public static function fromDashboard(DashboardDTO $d): self
    {
        $total = (int) ($d->get('total_sales') ?? 0);
        $avg = (int) ($d->get('avg_transaction') ?? 0);

        return new self(
            outletId: $d->outletId,
            date: $d->date,
            totalSales: $total,
            avgTransaction: $avg,
            avgCustomerSpending: (int) ($d->get('avg_customer_spending') ?? 0),
            txnCount: self::deriveTxnCount($d, $total, $avg),
            volumes: array_map('intval', (array) ($d->get('unit_volumes') ?? [])),
        );
    }

    /** Konsisten: pakai txn_count bila ada; jika tidak, total÷avg; jika tidak, jumlah order_type_summary. */
    private static function deriveTxnCount(DashboardDTO $d, int $total, int $avg): int
    {
        $count = (int) ($d->get('txn_count') ?? 0);
        if ($count > 0) {
            return $count;
        }
        if ($avg > 0) {
            return (int) round($total / $avg);
        }

        return (int) array_sum((array) ($d->get('order_type_summary') ?? []));
    }

    /** Untuk token renderer (OPS-203/903). */
    public function toTokens(): array
    {
        return [
            'total_sales' => $this->totalSales,
            'avg_transaction' => $this->avgTransaction,
            'avg_customer_spending' => $this->avgCustomerSpending,
            'txn_count' => $this->txnCount,
            'volume_kg' => $this->volumes['kg'] ?? 0,
            'volume_pcs' => $this->volumes['pcs'] ?? 0,
        ];
    }
}
