<?php

namespace App\Modules\Signals;

use App\Models\Outlet;
use Carbon\CarbonInterface;

/**
 * Rekomendasi transfer ke hub lapang saat overload (OPS-1104, System Design §3.14).
 * Sarankan hub dengan utilisasi terendah & kapasitas sisa cukup (semantik transfer_order
 * NEVIRA: sender = outlet overload → production = hub kandidat).
 *
 * ⚠️ REKOMENDASI SAJA — TIDAK mengeksekusi transfer otomatis. Komisi & aksi transfer tetap
 * keputusan manusia di NEVIRA. Hanya membaca beban outlet (OutletLoad), tanpa efek samping.
 */
final class TransferRecommender
{
    private const DEFAULT_LIMIT = 3;

    public function __construct(private readonly OutletLoad $load) {}

    /**
     * @return array{excess_kg_per_hour:float,note:string,candidates:array<int,array{id_outlet:int,name:string,utilization_pct:float,spare_kg_per_hour:float,can_absorb:bool}>}
     */
    public function recommend(int $overloadedOutletId, CarbonInterface|string $now, int $limit = self::DEFAULT_LIMIT): array
    {
        $subject = $this->load->forOutlet($overloadedOutletId, $now);
        $excess = $subject ? max(0.0, $subject['demand_kg_per_hour'] - $subject['capacity_kg_per_hour']) : 0.0;

        $candidates = Outlet::query()
            ->where('active', true)
            ->where('id_outlet', '!=', $overloadedOutletId)
            ->whereHas('capacity')
            ->get()
            ->map(fn (Outlet $o) => $this->load->forOutlet((int) $o->id_outlet, $now))
            ->filter() // kapasitas terkonfigurasi
            ->filter(fn (array $l) => $l['utilization'] < $l['threshold_pct'] / 100) // hub tak sedang sibuk
            ->filter(fn (array $l) => $l['spare_kg_per_hour'] > 0)                    // ada kapasitas sisa
            ->sortBy('utilization')                                                   // utilisasi terendah dulu
            ->take($limit)
            ->map(fn (array $l) => [
                'id_outlet' => $l['id_outlet'],
                'name' => $l['name'],
                'utilization_pct' => round($l['utilization'] * 100, 1),
                'spare_kg_per_hour' => round($l['spare_kg_per_hour'], 2),
                'can_absorb' => $l['spare_kg_per_hour'] >= $excess, // cukup menyerap kelebihan beban
            ])
            ->values()
            ->all();

        return [
            'excess_kg_per_hour' => round($excess, 2),
            'note' => 'Rekomendasi saja — transfer dieksekusi manual di NEVIRA (transfer_order).',
            'candidates' => $candidates,
        ];
    }
}
