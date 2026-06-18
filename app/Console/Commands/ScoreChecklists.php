<?php

namespace App\Console\Commands;

use App\Modules\Discipline\ComplianceScorer;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * M3-04 · hitung skor kepatuhan checklist (run + agregat periode → KPI Head Store). Self-monitored.
 * Jalan akhir hari setelah jendela deadline (M3-03).
 */
class ScoreChecklists extends Command
{
    protected $signature = 'oms:score-checklists {--date=}';

    protected $description = 'Hitung skor kepatuhan checklist per outlet/periode (M3-04).';

    public function handle(ComplianceScorer $scorer): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->format('Y-m-d');

        try {
            $scorer->scoreDate($date);
            $this->info("Skor kepatuhan {$date} dihitung.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('checklist.scoring_failed', ['date' => $date, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
