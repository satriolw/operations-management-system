<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Modules\Reporting\OutletCalendar;
use App\Modules\Signals\LateOrderDetector;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * Epic M (OPS-1303/1304) · cek nota terlambat/macet tiap outlet buka. Reuse activeOrders.
 * Self-monitored.
 */
class CheckLateOrders extends Command
{
    protected $signature = 'oms:check-late-orders {--date=}';

    protected $description = 'Deteksi nota terlambat & macet (LATE_ORDER) per outlet (Epic M).';

    public function handle(LateOrderDetector $detector, OutletCalendar $calendar): int
    {
        $now = $this->option('date') ? Wib::parse($this->option('date')) : Wib::normalize(now());
        $date = $now->toDateString();
        $total = 0;

        foreach (Outlet::query()->where('active', true)->pluck('id_outlet') as $idOutlet) {
            if ($calendar->isClosed((int) $idOutlet, $date)) {
                continue; // tutup → tak evaluasi (jam operasional nol)
            }
            try {
                $total += $detector->detect((int) $idOutlet, $now)->count();
            } catch (Throwable $e) {
                Alerter::raise('late_orders.detect_failed', ['id_outlet' => $idOutlet, 'message' => $e->getMessage()]);
            }
        }

        $this->info("Nota terlambat dievaluasi {$date}: {$total} sinyal.");

        return self::SUCCESS;
    }
}
