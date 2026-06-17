<?php

namespace App\Modules\Signals;

use App\Models\ReviewLog;
use App\Models\SignalEvent;
use App\Models\User;
use App\Modules\Signals\Exceptions\ReviewerIsSubject;
use Illuminate\Support\Facades\DB;

/**
 * Tinjauan sinyal (OPS-606): catat jejak append-only + ubah status sinyal. Bukan approval berlapis.
 * Reviewer ≠ subjek: bila user OMS tertaut ke aktor NEVIRA yang jadi subjek sinyal → tolak (eskalasi).
 */
final class ReviewService
{
    /** outcome → status sinyal */
    private const OUTCOME_STATUS = [
        'wajar' => 'DISMISSED',
        'ditindaklanjuti' => 'REVIEWED',
        'eskalasi' => 'REVIEWED',
    ];

    public function reviewSignal(SignalEvent $signal, User $reviewer, string $outcome, string $note, ?string $evidence = null): ReviewLog
    {
        if ($this->reviewerIsSubject($signal, $reviewer)) {
            throw new ReviewerIsSubject('Reviewer adalah subjek sinyal — harus ditinjau orang lain (eskalasi).');
        }

        return DB::transaction(function () use ($signal, $reviewer, $outcome, $note, $evidence) {
            $log = ReviewLog::create([
                'subject_type' => ReviewLog::SUBJECT_SIGNAL,
                'subject_id' => $signal->id,
                'reviewer_user_id' => $reviewer->id,
                'outcome' => $outcome,
                'note' => $note,
                'evidence_path' => $evidence,
                'reviewed_at' => now(),
            ]);

            $signal->update(['status' => self::OUTCOME_STATUS[$outcome] ?? 'REVIEWED']);

            return $log;
        });
    }

    private function reviewerIsSubject(SignalEvent $signal, User $reviewer): bool
    {
        // Hanya bisa dipastikan bila user OMS tertaut ke aktor NEVIRA.
        return $reviewer->nevira_user_id !== null
            && in_array((int) $reviewer->nevira_user_id, $signal->subjectActorIds(), true);
    }
}
