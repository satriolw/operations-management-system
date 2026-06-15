<?php

namespace App\Console\Commands;

use App\Support\Privacy\RetentionPurger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retensi data turunan (OPS-705). Bersihkan payload mentah report_runs & signal_events
 * melewati ambang umur (config/retention.php). Dijadwalkan harian (routes/console.php).
 */
class PurgeRawPayloads extends Command
{
    protected $signature = 'oms:purge-raw-payloads';

    protected $description = 'Bersihkan payload mentah melewati ambang retensi (OPS-705).';

    public function handle(RetentionPurger $purger): int
    {
        $reportDays = (int) config('retention.report_payload_days', 90);
        $signalDays = (int) config('retention.signal_payload_days', 180);

        $reports = $purger->purgeReportPayloads($reportDays);
        $signals = $purger->purgeSignalPayloads($signalDays);

        Log::info('OPS-705 retensi: payload mentah dibersihkan.', [
            'report_payloads_purged' => $reports,
            'report_payload_days' => $reportDays,
            'signal_payloads_purged' => $signals,
            'signal_payload_days' => $signalDays,
        ]);

        $this->info("Purged report payloads: {$reports}; signal payloads: {$signals}.");

        return self::SUCCESS;
    }
}
