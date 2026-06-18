<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\QtyVarianceScorer;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * OPS-1405 · agregasi variance quantity per kasir ke KPI input (cashier_input_scores). Periode bulan.
 * Bukan alert — memperkuat Epic F. Self-monitored.
 */
class ScoreQtyVariance extends Command
{
    protected $signature = 'oms:score-qty-variance {--month=}';

    protected $description = 'Agregasi variance quantity per kasir → KPI akurasi input (OPS-1405).';

    public function handle(QtyVarianceScorer $scorer): int
    {
        $month = $this->option('month') ?: Wib::normalize(now())->format('Y-m');
        $start = CarbonImmutable::parse($month.'-01', 'Asia/Jakarta')->startOfMonth();
        $range = new DateRange($start, $start->endOfMonth());

        try {
            foreach (Outlet::query()->where('active', true)->pluck('id_outlet') as $idOutlet) {
                $scorer->scan((int) $idOutlet, $range, $month);
            }
            $this->info("Variance quantity {$month} diagregasi ke KPI input.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('audit.qty_variance_failed', ['month' => $month, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
