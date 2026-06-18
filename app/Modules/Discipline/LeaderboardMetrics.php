<?php

namespace App\Modules\Discipline;

use App\Models\ComplianceScore;
use App\Models\Outlet;

/**
 * Rakit metrik mentah leaderboard per outlet (M3-05) lalu skor ternormalisasi (NormalizedScorer).
 *   growth%             = (rev - revPrior) / revPrior × 100   (null bila revPrior ≤ 0)
 *   revenue_per_capacity = rev ÷ effectiveKgPerHour (OPS-1101) (null bila kapasitas belum ada → tahan)
 *   compliance          = skor kepatuhan (M3-04) periode
 * Revenue di-supply pemanggil (M3-06 dari NEVIRA) agar modul ini tak terikat sumber.
 */
final class LeaderboardMetrics
{
    public function __construct(private readonly NormalizedScorer $scorer) {}

    /**
     * @param  array<int,float>  $revenueByOutlet  id_outlet → revenue periode
     * @param  array<int,float>  $priorRevenueByOutlet  id_outlet → revenue periode sebelumnya
     * @return array{rows:array<int,array<string,?float>>,scores:array<int,array{score:float,components:array<string,?float>}>}
     */
    public function build(string $period, array $revenueByOutlet, array $priorRevenueByOutlet): array
    {
        $compliance = ComplianceScore::query()->where('period', $period)->get()->keyBy('id_outlet');
        $capacities = Outlet::query()->with('capacity')->get()->keyBy('id_outlet');

        $rows = [];
        foreach ($revenueByOutlet as $idOutlet => $rev) {
            $rev = (float) $rev;
            $prior = (float) ($priorRevenueByOutlet[$idOutlet] ?? 0);
            $capKgH = optional($capacities->get($idOutlet)?->capacity)->effectiveKgPerHour();

            $rows[$idOutlet] = [
                'growth' => $prior > 0 ? round(($rev - $prior) / $prior * 100, 2) : null,
                'revenue_per_capacity' => ($capKgH && $capKgH > 0) ? round($rev / $capKgH, 4) : null, // tahan tanpa kapasitas
                'compliance' => $compliance->has($idOutlet) ? (float) $compliance->get($idOutlet)->score : null,
            ];
        }

        return [
            'rows' => $rows,
            'scores' => $this->scorer->compute($rows, (array) config('discipline.leaderboard_weights', [])),
        ];
    }
}
