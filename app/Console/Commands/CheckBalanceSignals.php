<?php

namespace App\Console\Commands;

use App\Modules\Signals\RunwayAlertCheck;
use App\Modules\Signals\TopupNudgeCheck;
use App\Support\Observability\Alerter;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-1204/1205 · evaluasi sinyal saldo NEVIRA: alert runway bertingkat (backstop) + nudge
 * kadens pengajuan (proaktif). Self-monitored — saldo = single point of failure jaringan.
 */
class CheckBalanceSignals extends Command
{
    protected $signature = 'oms:check-balance-signals {--date=}';

    protected $description = 'Cek alert runway (OPS-1204) + nudge pengajuan dana (OPS-1205) saldo NEVIRA.';

    public function handle(RunwayAlertCheck $runway, TopupNudgeCheck $nudge): int
    {
        $date = $this->option('date') ?: null;

        try {
            $alert = $runway->check($date);
            $nudged = $nudge->check($date);
            $this->info('Saldo signals: runway='.($alert?->payload_json['tier'] ?? 'ok').', nudge='.($nudged ? 'ya' : 'tidak').'.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('balance.signals_failed', ['message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
