<?php

namespace App\Modules\Discipline;

use App\Models\LeaderboardSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Bangun leaderboard periode (M3-06, System Design §6). Skor ternormalisasi (M3-05) lalu RATA-RATA
 * BERGERAK lintas N periode (anti-gaming: dorongan mepet deadline tak melonjakkan rank). Ranking
 * GLOBAL; persist snapshot. Scoping baca = LeaderboardSnapshot::visibleTo (controller).
 */
final class LeaderboardBuilder
{
    public function __construct(private readonly LeaderboardMetrics $metrics) {}

    /**
     * @param  array<int,float>  $revenueByOutlet
     * @param  array<int,float>  $priorRevenueByOutlet
     * @return Collection<int,LeaderboardSnapshot>  terurut rank
     */
    public function build(string $period, array $revenueByOutlet, array $priorRevenueByOutlet): Collection
    {
        $scores = $this->metrics->build($period, $revenueByOutlet, $priorRevenueByOutlet)['scores'];

        $window = max(1, (int) config('discipline.leaderboard_moving_avg_periods', 2));
        $priorLabels = $this->priorPeriods($period, $window - 1);

        // Rata-rata bergerak: komposit periode ini + raw_score periode-periode sebelumnya.
        $rows = collect($scores)->map(function (array $s, int $id) use ($priorLabels) {
            $raw = (float) $s['score'];
            $prior = LeaderboardSnapshot::query()
                ->where('id_outlet', $id)->whereIn('period', $priorLabels)
                ->pluck('raw_score')->map(fn ($x) => (float) $x)->all();

            return [
                'id_outlet' => $id,
                'raw' => $raw,
                'smoothed' => round((array_sum([$raw, ...$prior])) / (count($prior) + 1), 2),
                'breakdown' => $s['components'],
            ];
        })->sortByDesc('smoothed')->values();

        $result = collect();
        $rank = 0;
        foreach ($rows as $row) {
            $rank++;
            $result->push(LeaderboardSnapshot::updateOrCreate(
                ['period' => $period, 'id_outlet' => $row['id_outlet']],
                ['raw_score' => $row['raw'], 'score' => $row['smoothed'], 'rank' => $rank, 'metric_breakdown_json' => $row['breakdown']],
            ));
        }

        return $result;
    }

    /** @return array<int,string> label YYYY-MM sebelum $period (n periode) */
    private function priorPeriods(string $period, int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        $base = CarbonImmutable::parse($period.'-01', 'Asia/Jakarta');
        $labels = [];
        for ($i = 1; $i <= $n; $i++) {
            $labels[] = $base->subMonths($i)->format('Y-m');
        }

        return $labels;
    }
}
