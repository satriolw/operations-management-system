<?php

namespace App\Modules\Discipline;

use App\Models\ChecklistRun;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;

/**
 * Deadline checklist (M3-03, System Design §4): run lengkap → complete; lewat jam reminder & belum
 * lengkap → reminder; lewat jam eskalasi & belum lengkap → MISSED + eskalasi WhatsApp ke Head Store.
 * No-spam via Alerter::raiseOnce per (run, tahap). Jam reminder/eskalasi configurable.
 */
final class ChecklistDeadlineCheck
{
    public function evaluate(string $date, CarbonInterface|string|null $now = null): void
    {
        $at = $now === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $now));
        $hour = (int) $at->format('G');
        $reminderHour = (int) config('discipline.reminder_hour', 12);
        $escalationHour = (int) config('discipline.escalation_hour', 20);

        ChecklistRun::query()
            ->where('run_date', $date)
            ->where('status', ChecklistRun::STATUS_OPEN)
            ->with(['template.items', 'submissions'])
            ->get()
            ->each(function (ChecklistRun $run) use ($hour, $reminderHour, $escalationHour) {
                if ($this->isComplete($run)) {
                    $run->update(['status' => ChecklistRun::STATUS_COMPLETE]);

                    return;
                }

                if ($hour >= $escalationHour) {
                    $run->update(['status' => ChecklistRun::STATUS_MISSED]);
                    Alerter::raiseOnce("checklist.escalate:{$run->id}", 'checklist.missed', [
                        'id_outlet' => $run->id_outlet, 'run_id' => $run->id, 'run_date' => (string) $run->run_date,
                        'missing' => $this->missingCount($run),
                    ]); // eskalasi WA ke Head Store

                    return;
                }

                if ($hour >= $reminderHour) {
                    Alerter::raiseOnce("checklist.remind:{$run->id}", 'checklist.reminder', [
                        'id_outlet' => $run->id_outlet, 'run_id' => $run->id, 'missing' => $this->missingCount($run),
                    ], 'warning'); // reminder; tidak ubah status
                }
            });
    }

    private function isComplete(ChecklistRun $run): bool
    {
        return $this->missingCount($run) === 0;
    }

    /** Item belum tuntas: tak ada submission, atau wajib-foto tanpa photo_ref. */
    private function missingCount(ChecklistRun $run): int
    {
        $byItem = $run->submissions->keyBy('item_id');

        return $run->template->items->reduce(function (int $carry, $item) use ($byItem) {
            $sub = $byItem->get($item->id);
            $done = $sub !== null && (! $item->requires_photo || $sub->photo_ref !== null);

            return $carry + ($done ? 0 : 1);
        }, 0);
    }
}
