<?php

namespace App\Console\Commands;

use App\Modules\Discipline\ChecklistDeadlineCheck;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * M3-03 · evaluasi deadline checklist: complete / reminder / missed+eskalasi. Self-monitored.
 */
class CheckChecklistDeadlines extends Command
{
    protected $signature = 'oms:checklist-deadlines {--date=}';

    protected $description = 'Cek deadline checklist: reminder lalu eskalasi item terlewat (M3-03).';

    public function handle(ChecklistDeadlineCheck $check): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->format('Y-m-d');

        try {
            $check->evaluate($date);
            $this->info("Deadline checklist {$date} dievaluasi.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('checklist.deadline_failed', ['date' => $date, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
