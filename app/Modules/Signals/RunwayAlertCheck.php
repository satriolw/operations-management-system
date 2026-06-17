<?php

namespace App\Modules\Signals;

use App\Models\NeviraTopupConfig;
use App\Models\SignalEvent;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;

/**
 * Alert runway saldo NEVIRA bertingkat (OPS-1204, System Design §3.15) — BACKSTOP.
 * Ambang dinyatakan dalam HARI-RUNWAY (configurable, OPS-1203), bukan rupiah statis:
 *
 *   warning  bila runway ≤ warning_runway_days  (≈ gap + buffer)
 *   kritis   bila runway ≤ critical_runway_days (≈ gap maksimum)
 *
 * SALDO_NEVIRA = severity tertinggi, real-time ke owner/Finance (BUKAN digest) — saldo habis =
 * seluruh jaringan berhenti. Idempoten per hari; eskalasi warning→kritis memicu alert ulang.
 * Sinyal tingkat-merchant (id_outlet null). Rupiah disimpan untuk intuisi.
 */
final class RunwayAlertCheck
{
    public function __construct(
        private readonly BurnRateCalculator $burn,
        private readonly SignalRouter $router,
    ) {}

    public function check(CarbonInterface|string|null $asOf = null): ?SignalEvent
    {
        $br = $this->burn->compute($asOf);
        if ($br === null || $br->runwayDays === null) {
            return null; // data kurang, atau burn 0 (tak ada konsumsi → aman)
        }

        $cfg = NeviraTopupConfig::current();
        $tier = $this->tierFor($br->runwayDays, $cfg);
        if ($tier === null) {
            return null; // runway di atas ambang warning
        }

        return $this->raise($br, $cfg, $tier, $asOf);
    }

    private function tierFor(float $runwayDays, NeviraTopupConfig $cfg): ?string
    {
        if ($runwayDays <= $cfg->critical_runway_days) {
            return 'critical';
        }
        if ($runwayDays <= $cfg->warning_runway_days) {
            return 'warning';
        }

        return null;
    }

    private function raise(\App\Modules\Signals\DTO\BurnRunway $br, NeviraTopupConfig $cfg, string $tier, CarbonInterface|string|null $asOf): SignalEvent
    {
        $day = ($asOf === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $asOf)))->startOfDay();

        $signal = SignalEvent::firstOrNew([
            'id_outlet' => null, 'type' => 'SALDO_NEVIRA', 'detected_at' => $day,
        ]);

        $escalated = $signal->exists
            && ($signal->payload_json['tier'] ?? null) !== 'critical'
            && $tier === 'critical';

        // Baru, atau eskalasi warning→kritis → set & alert ulang. Same-tier re-run → no-op (no dup alert).
        if (! $signal->exists || $escalated) {
            $signal->fill([
                'severity' => 'high', // real-time, bukan digest (saldo = single point of failure)
                'status' => 'OPEN',
                'payload_json' => [
                    'tier' => $tier,
                    'runway_days' => $br->runwayDays,
                    'burn_per_day' => $br->burnPerDay,
                    'saldo_total' => $br->saldoTotal,      // rupiah, untuk intuisi
                    'warning_runway_days' => $cfg->warning_runway_days,
                    'critical_runway_days' => $cfg->critical_runway_days,
                    'evaluated_date' => $day->format('Y-m-d'),
                ],
            ])->save();

            $this->router->notify($signal);
        }

        return $signal;
    }
}
