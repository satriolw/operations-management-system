<?php

namespace App\Modules\Reporting;

use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Reporting\DTO\RevenueSplit;
use Carbon\CarbonInterface;

/**
 * Pisahkan Terealisasi vs Piutang (OPS-202). Piutang = Σ grand_total transaksi UNPAID
 * pada (outlet, tanggal); Terealisasi = total_sales − piutang. Lewat TransactionSource.
 */
final class RevenueSplitter
{
    public function __construct(private readonly TransactionSource $source) {}

    public function forOutlet(int $idOutlet, CarbonInterface|string $date, int $totalSales): RevenueSplit
    {
        $range = new DateRange($date, $date);

        $piutang = (int) $this->source->unpaid($idOutlet, $range)
            ->sum(fn ($row) => (int) ($row['grand_total'] ?? 0));

        return new RevenueSplit(
            totalSales: $totalSales,
            realized: $totalSales - $piutang,
            receivable: $piutang,
        );
    }
}
