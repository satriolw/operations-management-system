<?php

namespace App\Modules\Discipline;

use App\Models\Outlet;
use App\Modules\Ingestion\Contracts\TransactionSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rakit revenue periode per outlet dari NEVIRA (M3-06) — sumber growth & revenue/kapasitas (M3-05).
 * Menjumlahkan total_sales harian (anti-corruption via TransactionSource). Dipakai bulanan (leaderboard).
 */
final class RevenueByOutlet
{
    public function __construct(private readonly TransactionSource $source) {}

    /** @return array<int,float> id_outlet → total revenue periode (YYYY-MM) */
    public function forPeriod(string $period): array
    {
        $start = CarbonImmutable::parse($period.'-01', 'Asia/Jakarta')->startOfMonth();

        return $this->forRange($start, $start->endOfMonth());
    }

    /** @return array<int,float> */
    public function forRange(CarbonInterface $start, CarbonInterface $end): array
    {
        $map = [];
        foreach (Outlet::query()->where('active', true)->get() as $outlet) {
            $sum = 0.0;
            $cursor = CarbonImmutable::instance($start);
            $last = CarbonImmutable::instance($end);
            while ($cursor->lte($last)) {
                $sum += (float) ($this->source->dailyDashboard((int) $outlet->id_outlet, $cursor->format('Y-m-d'))->get('total_sales') ?? 0);
                $cursor = $cursor->addDay();
            }
            $map[(int) $outlet->id_outlet] = $sum;
        }

        return $map;
    }
}
