<?php

namespace App\Console\Commands;

use App\Modules\Delivery\DeliveryWatchdog;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-704 · jalankan watchdog pengiriman. Self-monitored: kegagalan watchdog sendiri → alert
 * (watchdog tidak ikut gagal diam-diam).
 */
class WatchdogDeliveries extends Command
{
    protected $signature = 'oms:watchdog-deliveries {--date=}';

    protected $description = 'Verifikasi tiap outlet aktif punya laporan TERKONFIRMASI terkirim (OPS-704).';

    public function handle(DeliveryWatchdog $watchdog): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->format('Y-m-d');

        try {
            $missing = $watchdog->check($date);
            $this->info("Watchdog {$date}: ".count($missing).' outlet tanpa laporan terkonfirmasi.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('watchdog.failed', ['report_date' => $date, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
