<?php

namespace App\Modules\Reporting;

use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Reporting\DTO\DailyMetrics;
use Carbon\CarbonInterface;

/**
 * Ambil & agregasi dashboard harian NEVIRA (OPS-201). Lewat TransactionSource (anti-corruption),
 * bukan klien konkret. Mengembalikan metrik turunan; tak menyimpan kebenaran transaksi.
 */
final class DailyDashboardAggregator
{
    public function __construct(private readonly TransactionSource $source) {}

    public function forOutlet(int $idOutlet, CarbonInterface|string $date): DailyMetrics
    {
        return DailyMetrics::fromDashboard($this->source->dailyDashboard($idOutlet, $date));
    }
}
