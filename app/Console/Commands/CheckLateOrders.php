<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Modules\Ingestion\PollScheduler;
use App\Modules\Signals\LateOrderDetector;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * Epic M (OPS-1303/1304) · cek nota terlambat/macet. Adaptive polling (OPS-109): dijadwalkan rapat
 * (tiap 10 menit) tapi PollScheduler menggerbang per outlet — hanya yang BUKA sekarang & sudah
 * melewati cadence efektif (watermark). Latensi turun saat ramai, diam saat tutup. Reuse activeOrders.
 */
class CheckLateOrders extends Command
{
    protected $signature = 'oms:check-late-orders {--date=} {--force : abaikan watermark/jam (manual)}';

    protected $description = 'Deteksi nota terlambat & macet (LATE_ORDER) per outlet (Epic M).';

    public function handle(LateOrderDetector $detector, PollScheduler $poll): int
    {
        $now = $this->option('date') ? Wib::parse($this->option('date')) : Wib::normalize(now());
        $force = (bool) $this->option('force');
        $checked = 0;
        $total = 0;

        foreach (Outlet::query()->where('active', true)->pluck('id_outlet') as $idOutlet) {
            $idOutlet = (int) $idOutlet;
            if (! $force && ! $poll->shouldPoll('late_orders', $idOutlet, $now)) {
                continue; // tutup sekarang / belum lewat cadence → no-op (hemat API)
            }
            try {
                $total += $detector->detect($idOutlet, $now)->count();
                $poll->markPolled('late_orders', $idOutlet, $now); // watermark hanya pada poll sukses
                $checked++;
            } catch (Throwable $e) {
                Alerter::raise('late_orders.detect_failed', ['id_outlet' => $idOutlet, 'message' => $e->getMessage()]);
            }
        }

        $this->info("Nota terlambat: {$checked} outlet dipoll, {$total} sinyal ({$now->toDateTimeString()} WIB).");

        return self::SUCCESS;
    }
}
