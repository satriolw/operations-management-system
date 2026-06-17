<?php

namespace App\Modules\Signals;

use App\Models\Outlet;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Model beban outlet (OPS-1103/1104, System Design §3.14). Load = backlog aktif ditimbang sisa
 * waktu ke deadline (BUKAN kg mentah). Dipakai bersama OverloadCheck (sinyal) & TransferRecommender
 * (rekomendasi hub). Hanya BACA (activeOrders) — tak ada efek samping/auto-transfer.
 *
 *   utilization = Σ (sisa_kg_i / jam_ke_deadline_i) ÷ capacity_kg_per_hour
 */
final class OutletLoad
{
    /** Floor jam_ke_deadline: order overdue/express tetap berkontribusi besar, hindari /0 & negatif. */
    private const MIN_HOURS_TO_DEADLINE = 0.5;

    public function __construct(private readonly TransactionSource $source) {}

    /**
     * Metrik beban satu outlet. Null bila kapasitas belum dikonfigurasi (OPS-1101).
     *
     * @return array{id_outlet:int,name:string,capacity_kg_per_hour:float,demand_kg_per_hour:float,utilization:float,threshold_pct:int,spare_kg_per_hour:float,active_orders:int}|null
     */
    public function forOutlet(int $idOutlet, CarbonInterface|string $now): ?array
    {
        $outlet = Outlet::with('capacity')->find($idOutlet);
        $capacity = $outlet?->capacity?->effectiveKgPerHour();
        if (! $capacity) {
            return null;
        }

        $at = $this->at($now);
        $orders = $this->source->activeOrders($idOutlet);
        $demand = $this->demandKgPerHour($orders, $at);
        $utilization = $demand / $capacity;

        return [
            'id_outlet' => (int) $outlet->id_outlet,
            'name' => (string) $outlet->name,
            'capacity_kg_per_hour' => (float) $capacity,
            'demand_kg_per_hour' => $demand,
            'utilization' => $utilization,
            'threshold_pct' => (int) ($outlet->capacity->overload_threshold_pct ?? 80),
            'spare_kg_per_hour' => max(0.0, $capacity - $demand), // ruang sampai 100%
            'active_orders' => $orders->count(),
        ];
    }

    public function at(CarbonInterface|string $now): CarbonInterface
    {
        return $now instanceof CarbonInterface ? Wib::normalize($now) : Wib::parse($now);
    }

    /** Total kebutuhan kg/jam dari backlog aktif, ditimbang sisa waktu ke deadline. */
    private function demandKgPerHour(Collection $orders, CarbonInterface $at): float
    {
        $sum = 0.0;

        foreach ($orders as $o) {
            $qty = (float) ($o['quantity'] ?? 0);
            $progress = (float) ($o['progress_percentage'] ?? 0);
            $remaining = $qty * max(0.0, 1 - $progress / 100);
            if ($remaining <= 0) {
                continue;
            }

            $sum += $remaining / $this->hoursToDeadline($o, $at);
        }

        return $sum;
    }

    /** Jam ke deadline (WIB tingkat-transaksi); di-floor agar overdue/express kontribusi besar. */
    private function hoursToDeadline(array $order, CarbonInterface $at): float
    {
        $eta = Wib::parseNullable($order['estimated_completion_date'] ?? null);
        if ($eta === null) {
            return self::MIN_HOURS_TO_DEADLINE; // tenggat tak diketahui → mendesak
        }

        $hours = $at->diffInRealSeconds($eta, false) / 3600; // signed: future positif, overdue negatif

        return max(self::MIN_HOURS_TO_DEADLINE, $hours);
    }
}
