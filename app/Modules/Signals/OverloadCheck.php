<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Support\Time\Wib;

/**
 * Deteksi overload outlet (OPS-1103, System Design §3.14) — kembar outlet-diam (over-utilized).
 * Beban dihitung OutletLoad (deadline-weighted). Ambang per-outlet (OPS-1101):
 *
 *     warning  bila utilization ≥ ambang_outlet → severity LOW (digest)
 *     overload bila utilization ≥ 100%          → severity HIGH (real-time)
 *
 * Sinyal OVERLOAD ke signal_events (idempoten per outlet+jam), severity-tiered (OPS-1002).
 * Pada overload, payload menyertakan rekomendasi hub (OPS-1104) — REKOMENDASI, bukan auto-transfer.
 * Payload tanpa PII.
 */
final class OverloadCheck
{
    public function __construct(
        private readonly OutletLoad $load,
        private readonly SignalRouter $router,
        private readonly TransferRecommender $recommender,
    ) {}

    public function check(int $idOutlet, \Carbon\CarbonInterface|string $now): ?SignalEvent
    {
        $load = $this->load->forOutlet($idOutlet, $now);
        if ($load === null) {
            return null; // kapasitas belum dikonfigurasi (OPS-1101) → tak bisa hitung
        }

        $severity = $this->severityFor($load['utilization'], $load['threshold_pct']);
        if ($severity === null) {
            return null; // di bawah ambang warning → bukan sinyal
        }

        return $this->raise($idOutlet, $this->load->at($now), $load, $severity);
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

    private function raise(int $idOutlet, \Carbon\CarbonInterface $at, array $load, string $severity): SignalEvent
    {
        $detectedAt = Wib::normalize($at)->startOfHour(); // idempoten per outlet+jam
        $isOverload = $load['utilization'] >= 1.0;

        $payload = [ // metrik kapasitas, tanpa PII customer
            'utilization_pct' => round($load['utilization'] * 100, 1),
            'demand_kg_per_hour' => round($load['demand_kg_per_hour'], 2),
            'capacity_kg_per_hour' => round($load['capacity_kg_per_hour'], 2),
            'threshold_pct' => $load['threshold_pct'],
            'active_orders' => $load['active_orders'],
            'tier' => $isOverload ? 'overload' : 'warning',
            'evaluated_at' => $at->format('Y-m-d H:i'),
        ];

        // OPS-1104: rekomendasi transfer hanya saat overload (high). Rekomendasi SAJA — manusia/NEVIRA eksekusi.
        if ($isOverload) {
            $payload['transfer_recommendation'] = $this->recommender->recommend($idOutlet, $at);
        }

        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'OVERLOAD', 'detected_at' => $detectedAt],
            ['severity' => $severity, 'status' => 'OPEN', 'payload_json' => $payload],
        );

        // OPS-1002: HIGH (overload) real-time; LOW (warning) → digest, tak ada notifikasi per-kejadian.
        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal);
        }

        return $signal;
    }
}
