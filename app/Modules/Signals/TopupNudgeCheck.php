<?php

namespace App\Modules\Signals;

use App\Models\NeviraTopupConfig;
use App\Models\SignalEvent;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;

/**
 * Nudge kadens pengajuan dana saldo NEVIRA (OPS-1205, System Design §3.15) — PROAKTIF.
 * Sebelum tiap cutoff Senin/Kamis: bila SKIP window ini, dana berikutnya baru cair di window
 * setelahnya → cek apakah saldo cukup bertahan sampai window-setelahnya + buffer. Bila tidak →
 * prompt "ajukan dana untuk pencairan ini".
 *
 *   horizon = hari ke window-setelah-target + buffer (Kamis: buffer EKSTRA, menjaga akhir pekan)
 *   nudge bila runway < horizon
 *
 * Bottleneck = jadwal Finance, bukan NEVIRA. Idempoten per window (tak spam). Merchant-level
 * (id_outlet null). Reuse BurnRateCalculator (OPS-1202) + NeviraTopupConfig (OPS-1203).
 */
final class TopupNudgeCheck
{
    /** Buffer ekstra untuk window Kamis (gap Kamis→Senin melintasi akhir pekan, burn tinggi). */
    private const THURSDAY_WEEKEND_BUFFER_DAYS = 2;

    public function __construct(
        private readonly BurnRateCalculator $burn,
        private readonly SignalRouter $router,
    ) {}

    public function check(CarbonInterface|string|null $asOf = null): ?SignalEvent
    {
        $now = $asOf === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $asOf));
        $cfg = NeviraTopupConfig::current();

        $ups = $cfg->upcomingDisbursements($now, 4);
        if (count($ups) < 2) {
            return null;
        }

        $leadHours = (int) $cfg->submission_cutoff_lead_hours;

        // Target = window terdekat yang cutoff-nya BELUM lewat (masih bisa diajukan).
        $targetIdx = null;
        foreach ($ups as $i => $w) {
            if ($now->lte($w->subHours($leadHours))) {
                $targetIdx = $i;
                break;
            }
        }
        if ($targetIdx === null || ! isset($ups[$targetIdx + 1])) {
            return null;
        }

        $window = $ups[$targetIdx];
        $after = $ups[$targetIdx + 1]; // dana berikutnya bila skip window ini

        $buffer = (int) $cfg->buffer_days
            + ($window->dayOfWeek === CarbonInterface::THURSDAY ? self::THURSDAY_WEEKEND_BUFFER_DAYS : 0);
        $horizon = (int) ceil($now->floatDiffInDays($after)) + $buffer;

        $br = $this->burn->compute($now);
        if ($br === null || $br->runwayDays === null) {
            return null; // data kurang / burn 0 → tak ada proyeksi tak-aman
        }

        if ($br->runwayDays >= $horizon) {
            return null; // proyeksi aman sampai window berikutnya + buffer
        }

        return $this->raise($window, $after, $buffer, $horizon, $br);
    }

    private function raise($window, $after, int $buffer, int $horizon, \App\Modules\Signals\DTO\BurnRunway $br): SignalEvent
    {
        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => null, 'type' => 'TOPUP_NUDGE', 'detected_at' => $window->startOfDay()],
            [
                'severity' => 'high', // proaktif, time-sensitive (sebelum cutoff)
                'status' => 'OPEN',
                'payload_json' => [
                    'message' => 'Ajukan dana untuk pencairan ini — proyeksi saldo tak cukup sampai window berikutnya.',
                    'window_date' => $window->format('Y-m-d'),
                    'window_weekday' => $window->dayOfWeek,
                    'next_window_date' => $after->format('Y-m-d'),
                    'horizon_days' => $horizon,
                    'buffer_days' => $buffer,
                    'runway_days' => $br->runwayDays,
                    'saldo_total' => $br->saldoTotal, // rupiah, untuk intuisi
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal); // satu per window (idempoten) → tak spam
        }

        return $signal;
    }
}
