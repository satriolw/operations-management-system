<?php

namespace App\Console\Commands;

use App\Modules\Signals\BalanceSnapshotService;
use App\Support\Observability\Alerter;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-1201 · tangkap snapshot saldo merchant NEVIRA berkala (Epic L). Self-monitored:
 * kegagalan capture → alert (saldo = single point of failure jaringan, jangan gagal diam-diam).
 */
class CaptureBalanceSnapshot extends Command
{
    protected $signature = 'oms:capture-balance-snapshot {--date=}';

    protected $description = 'Tangkap snapshot saldo deposit merchant NEVIRA (OPS-1201).';

    public function handle(BalanceSnapshotService $service): int
    {
        try {
            $snap = $service->capture($this->option('date') ?: null);
            $this->info("Snapshot saldo: Rp{$snap->saldo_total} @ {$snap->captured_at}.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('balance.snapshot_failed', ['message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
