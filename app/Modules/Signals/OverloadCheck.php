<?php

namespace App\Modules\Signals;

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Deteksi overload outlet (OPS-1103, System Design §3.14) — kembar outlet-diam (over-utilized).
 * Load = backlog aktif ditimbang sisa waktu ke deadline (BUKAN kg mentah):
 *
 *   utilization = Σ (sisa_kg_i / jam_ke_deadline_i)  ÷  capacity_kg_per_hour
 *     warning  bila utilization ≥ ambang_outlet  → severity LOW (digest)
 *     overload bila utilization ≥ 100%           → severity HIGH (real-time)
 *
 * Order express (deadline dekat) otomatis berkontribusi lebih besar (jam_ke_deadline kecil).
 * Sinyal OVERLOAD ke signal_events (idempoten per outlet+jam), severity-tiered (OPS-1002).
 * Aksi = alert + rekomendasi transfer (OPS-1104) — BUKAN auto-transfer. Payload tanpa PII.
 */
final class OverloadCheck
{
    /** Floor jam_ke_deadline: order overdue/express tetap berkontribusi besar, hindari /0 & nilai negatif. */
    private const MIN_HOURS_TO_DEADLINE = 0.5;

    public function __construct(
        private readonly TransactionSource $source,
        private readonly SignalRouter $router,
    ) {}

    public function check(int $idOutlet, CarbonInterface|string $now): ?SignalEvent
    {
        $outlet = Outlet::with('capacity')->find($idOutlet);
        $capacity = $outlet?->capacity?->effectiveKgPerHour();
        if (! $capacity) {
            return null; // kapasitas belum dikonfigurasi (OPS-1101) → tak bisa hitung utilisasi
        }

        $at = $now instanceof CarbonInterface ? Wib::normalize($now) : Wib::parse($now);
        $orders = $this->source->activeOrders($idOutlet);
        $demand = $this->demandKgPerHour($orders, $at);
        $utilization = $demand / $capacity;

        $thresholdPct = (int) ($outlet->capacity->overload_threshold_pct ?? 80);
        $severity = $this->severityFor($utilization, $thresholdPct);
        if ($severity === null) {
            return null; // di bawah ambang warning → bukan sinyal
        }

        return $this->raise($outlet, $at, $utilization, $demand, $capacity, $thresholdPct, $severity, $orders->count());
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
                continue; // praktis selesai → tak menambah beban
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
            return self::MIN_HOURS_TO_DEADLINE; // tenggat tak diketahui → perlakukan mendesak
        }

        $hours = $at->diffInRealSeconds($eta, false) / 3600; // signed: future positif, overdue negatif

        return max(self::MIN_HOURS_TO_DEADLINE, $hours);
    }

    /** overload ≥ 100% → high; warning ≥ ambang → low; selain itu null. */
    private function severityFor(float $utilization, int $thresholdPct): ?string
    {
        if ($utilization >= 1.0) {
            return 'high'; // overload, real-time
        }
        if ($utilization >= $thresholdPct / 100) {
            return 'low'; // warning, digest
        }

        return null;
    }

    private function raise(
        Outlet $outlet,
        CarbonInterface $at,
        float $utilization,
        float $demand,
        float $capacity,
        int $thresholdPct,
        string $severity,
        int $activeOrders,
    ): SignalEvent {
        $detectedAt = Wib::normalize($at)->startOfHour(); // idempoten per outlet+jam

        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $outlet->id_outlet, 'type' => 'OVERLOAD', 'detected_at' => $detectedAt],
            [
                'severity' => $severity,
                'status' => 'OPEN',
                'payload_json' => [ // metrik kapasitas, tanpa PII customer
                    'utilization_pct' => round($utilization * 100, 1),
                    'demand_kg_per_hour' => round($demand, 2),
                    'capacity_kg_per_hour' => round($capacity, 2),
                    'threshold_pct' => $thresholdPct,
                    'active_orders' => $activeOrders,
                    'tier' => $utilization >= 1.0 ? 'overload' : 'warning',
                    'evaluated_at' => $at->format('Y-m-d H:i'),
                ],
            ],
        );

        // OPS-1002: HIGH (overload) real-time; LOW (warning) → digest, tak ada notifikasi per-kejadian.
        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal);
        }

        return $signal;
    }
}
