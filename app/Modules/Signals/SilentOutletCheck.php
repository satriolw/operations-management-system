<?php

namespace App\Modules\Signals;

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Reporting\OutletCalendar;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;

/**
 * Deteksi outlet-diam pada satu titik cek (OPS-502). Bandingkan transaksi berjalan hari ini vs
 * baseline (OPS-501) terhadap ambang outlet (OPS-803). Hari libur/tutup TIDAK memicu alarm (OPS-106).
 * Outlet baru (baseline sample minim) → konservatif: hanya alarm bila benar-benar nol transaksi.
 * Sinyal disimpan ke signal_event (idempoten per outlet+titik-cek+hari) + alert real-time (severity high).
 */
final class SilentOutletCheck
{
    private const MIN_SAMPLE = 14; // di bawah ini, baseline belum dipercaya

    public function __construct(
        private readonly TransactionSource $source,
        private readonly OutletCalendar $calendar,
    ) {}

    public function check(int $idOutlet, string $today, int $checkpointHour): ?SignalEvent
    {
        // OPS-106: hari libur/tutup → tidak ada alarm.
        if ($this->calendar->isClosed($idOutlet, $today)) {
            return null;
        }

        $realized = (int) ($this->source->dailyDashboard($idOutlet, $today)->get('txn_count') ?? 0);
        $thresholdPct = (int) (Outlet::query()->whereKey($idOutlet)->value('silent_threshold_pct') ?? 40);
        $baseline = OutletBaseline::query()
            ->where('id_outlet', $idOutlet)->where('checkpoint_hour', $checkpointHour)->first();

        if (! $this->isSilent($realized, $thresholdPct, $baseline)) {
            return null;
        }

        return $this->raise($idOutlet, $today, $checkpointHour, $realized, $thresholdPct, $baseline);
    }

    private function isSilent(int $realized, int $thresholdPct, ?OutletBaseline $baseline): bool
    {
        // Konservatif bila baseline minim/absen: hanya nol transaksi yang dianggap diam.
        if ($baseline === null || (int) $baseline->sample_days < self::MIN_SAMPLE) {
            return $realized === 0;
        }

        $expected = (float) $baseline->avg_txn * ($thresholdPct / 100);

        return $realized < $expected;
    }

    private function raise(int $idOutlet, string $today, int $hour, int $realized, int $pct, ?OutletBaseline $baseline): SignalEvent
    {
        $detectedAt = Wib::parse($today)->setTime($hour, 0); // deterministik → idempoten per titik cek/hari

        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'SILENT_OUTLET', 'detected_at' => $detectedAt],
            [
                'severity' => 'high', // real-time (System Design §3.13 severity tiering)
                'status' => 'OPEN',
                'payload_json' => [ // metrik operasional, tanpa PII customer
                    'checkpoint_hour' => $hour,
                    'realized' => $realized,
                    'baseline_avg' => $baseline ? (float) $baseline->avg_txn : null,
                    'threshold_pct' => $pct,
                    'date' => $today,
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            Alerter::raise('outlet.silent', [ // ke Head Store/Area Manager (real-time)
                'id_outlet' => $idOutlet, 'checkpoint_hour' => $hour, 'realized' => $realized, 'date' => $today,
            ]);
        }

        return $signal;
    }
}
