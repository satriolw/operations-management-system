<?php

namespace App\Console\Commands;

use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\CostAttributionService;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-1206 · atribusi biaya saldo NEVIRA per outlet (Epic L, P2). Periodik (default bulan lalu).
 * History DI-CHUNK per hari oleh service (hindari page-cap). Self-monitored.
 */
class AttributeBalanceCost extends Command
{
    protected $signature = 'oms:attribute-balance-cost {--start=} {--end=}';

    protected $description = 'Hitung biaya saldo NEVIRA per outlet + flag burn abnormal (OPS-1206).';

    public function handle(CostAttributionService $service): int
    {
        $today = Wib::normalize(now());
        $start = $this->option('start') ?: $today->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
        $end = $this->option('end') ?: $today->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');

        try {
            $rows = $service->attribute(new DateRange($start, $end));
            $flagged = $rows->where('flagged', true)->count();
            $this->info("Atribusi biaya {$start}..{$end}: {$rows->count()} outlet, {$flagged} ter-flag.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('balance.cost_attribution_failed', ['start' => $start, 'end' => $end, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
