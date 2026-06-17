<?php

namespace App\Modules\Signals;

use App\Models\OutletBaseline;
use App\Models\OutletCheckpoint;
use App\Models\ReportRun;
use App\Modules\Reporting\OutletCalendar;
use App\Support\Time\Wib;

/**
 * Hitung baseline transaksi per outlet untuk deteksi outlet-diam (OPS-501).
 *
 * ANTI-BIAS: baseline HANYA dari hari BUKA & BERTRANSAKSI (buang hari libur/tutup via OutletCalendar
 * dan hari nol-transaksi) selama ~30 hari, agar ambang tidak terseret turun. Disimpan per titik cek
 * (jam dari konfigurasi outlet OPS-803, bukan hardcode). Outlet baru: sample_days kecil → OPS-502 pakai
 * ambang konservatif.
 */
final class BaselineCalculator
{
    private const WINDOW_DAYS = 30;

    public function __construct(private readonly OutletCalendar $calendar) {}

    /** @return array{avg:float,sample_days:int,checkpoints:int} */
    public function recompute(int $idOutlet, string $today): array
    {
        $checkpointHours = OutletCheckpoint::query()
            ->where('id_outlet', $idOutlet)
            ->pluck('check_time')
            ->map(fn ($t) => (int) substr((string) $t, 0, 2))
            ->unique()->values();

        $start = Wib::parse($today)->subDays(self::WINDOW_DAYS)->format('Y-m-d');
        $end = Wib::parse($today)->subDay()->format('Y-m-d'); // s/d kemarin

        // hari buka & bertransaksi saja
        $openDays = ReportRun::query()
            ->where('id_outlet', $idOutlet)
            ->whereBetween('report_date', [$start, $end])
            ->where('txn_count', '>', 0)
            ->get()
            ->reject(fn (ReportRun $r) => $this->calendar->isClosed($idOutlet, (string) $r->report_date));

        $avg = round((float) ($openDays->avg('txn_count') ?? 0), 2);
        $sample = $openDays->count();

        foreach ($checkpointHours as $hour) {
            OutletBaseline::updateOrCreate(
                ['id_outlet' => $idOutlet, 'checkpoint_hour' => $hour],
                ['avg_txn' => $avg, 'sample_days' => $sample],
            );
        }

        return ['avg' => $avg, 'sample_days' => $sample, 'checkpoints' => $checkpointHours->count()];
    }
}
