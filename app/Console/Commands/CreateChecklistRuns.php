<?php

namespace App\Console\Commands;

use App\Modules\Discipline\ChecklistScheduler;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * M3-03 · buat run checklist harian per outlet (idempoten). Self-monitored.
 */
class CreateChecklistRuns extends Command
{
    protected $signature = 'oms:create-checklist-runs {--date=}';

    protected $description = 'Buat run checklist harian per outlet dari template (M3-03).';

    public function handle(ChecklistScheduler $scheduler): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->format('Y-m-d');

        try {
            $runs = $scheduler->createDailyRuns($date);
            $this->info("Checklist runs {$date}: {$runs->count()} run.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('checklist.scheduler_failed', ['date' => $date, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
