<?php

namespace App\Modules\Signals;

use App\Models\NeviraCostByOutlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Support\Time\Wib;
use Illuminate\Support\Collection;

/**
 * Atribusi biaya saldo NEVIRA per outlet (OPS-1206, System Design §3.15). Dari id_outlet di history:
 * count per aksi + total_cost (count × unit_cost). Outlet dengan burn abnormal di-flag (perlu
 * ditinjau, BUKAN tuduhan) → sinyal COST_ANOMALY severity low (digest).
 *
 * History DI-CHUNK per hari (merchantCostHistory) agar tak menabrak page-cap (Epic L: ~66 hal/hari).
 * Periodik (P2) — bukan jalur runway.
 */
final class CostAttributionService
{
    public function __construct(private readonly TransactionSource $source) {}

    /**
     * Hitung & persist biaya per outlet untuk rentang; flag anomali. Idempoten per (outlet, period).
     *
     * @return Collection<int,NeviraCostByOutlet>
     */
    public function attribute(DateRange $range): Collection
    {
        $counts = $this->collectCounts($range);
        $period = $range->startDate().'_'.$range->endDate();
        $unit = (array) config('balance.unit_cost', []);

        $totals = [];
        foreach ($counts as $idOutlet => $byAction) {
            $totals[$idOutlet] = collect($byAction)->sum(fn ($n, $action) => $n * (int) ($unit[$action] ?? 0));
        }

        $flagged = $this->anomalousOutlets($totals);

        $rows = collect();
        foreach ($counts as $idOutlet => $byAction) {
            $row = NeviraCostByOutlet::updateOrCreate(
                ['id_outlet' => $idOutlet, 'period' => $period],
                ['counts_json' => $byAction, 'total_cost' => (int) $totals[$idOutlet], 'flagged' => in_array($idOutlet, $flagged, true)],
            );
            if ($row->flagged) {
                $this->raiseAnomaly($idOutlet, $period, $byAction, (int) $totals[$idOutlet]);
            }
            $rows->push($row);
        }

        return $rows;
    }

    /** Kumpulkan count aksi per outlet, CHUNK per hari (hindari page-cap). */
    private function collectCounts(DateRange $range): array
    {
        $counts = [];
        $cursor = $range->start->startOfDay();
        $end = $range->end->startOfDay();

        while ($cursor->lte($end)) {
            $day = new DateRange($cursor, $cursor);
            foreach ($this->source->merchantCostHistory($day) as $row) {
                $idOutlet = (int) ($row['id_outlet'] ?? 0);
                if ($idOutlet === 0) {
                    continue; // baris tanpa outlet (mis. biaya tingkat-merchant) → tak diatribusikan
                }
                $action = (string) ($row['action'] ?? 'unknown');
                $counts[$idOutlet][$action] = ($counts[$idOutlet][$action] ?? 0) + 1;
            }
            $cursor = $cursor->addDay();
        }

        return $counts;
    }

    /**
     * Outlet dengan total_cost > factor × median populasi (perlu ditinjau). Butuh populasi cukup.
     *
     * @return array<int,int> id_outlet ter-flag
     */
    private function anomalousOutlets(array $totals): array
    {
        $minOutlets = (int) config('balance.anomaly_min_outlets', 3);
        if (count($totals) < $minOutlets) {
            return []; // populasi kurang → tak ada basis perbandingan
        }

        $factor = (float) config('balance.anomaly_factor', 3.0);
        $median = $this->median(array_values($totals));
        if ($median <= 0) {
            return [];
        }

        return collect($totals)->filter(fn ($t) => $t > $factor * $median)->keys()->map(fn ($k) => (int) $k)->all();
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    private function raiseAnomaly(int $idOutlet, string $period, array $counts, int $totalCost): void
    {
        $endDate = explode('_', $period)[1] ?? $period; // periode = start_end → pakai tanggal akhir
        SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'COST_ANOMALY', 'detected_at' => Wib::parse($endDate)->startOfDay()],
            [
                'severity' => 'low', // perlu ditinjau, digest (bukan tuduhan)
                'status' => 'OPEN',
                'payload_json' => [
                    'period' => $period,
                    'counts' => $counts,
                    'total_cost' => $totalCost,
                    'note' => 'Burn saldo di atas wajar — perlu ditinjau (mis. spam struk/WhatsApp).',
                ],
            ],
        );
    }
}
