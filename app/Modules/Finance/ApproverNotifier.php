<?php

namespace App\Modules\Finance;

use App\Models\FinancialDocument;
use App\Models\User;
use App\Support\Observability\Alerter;

/**
 * Notifikasi approver berikutnya saat dokumen butuh persetujuan (M2-08). Reuse kanal observability
 * Modul 1 (Alerter → log + OpsAlertRaised, disambung WA-ops kelak). Idempoten via raiseOnce per
 * (document, level) → tidak spam. Payload tanpa PII (hanya doc_number/level/role/outlet).
 */
final class ApproverNotifier
{
    private const PENDING = [
        FinancialDocument::STATUS_SUBMITTED,
        FinancialDocument::STATUS_APPROVED_L1,
        FinancialDocument::STATUS_APPROVED_L2,
    ];

    public function __construct(private readonly ChainResolver $resolver) {}

    /** Beri tahu approver pada level berjalan (bila ada). Aman dipanggil berulang (idempoten). */
    public function notifyNext(FinancialDocument $doc): bool
    {
        if (! in_array($doc->status, self::PENDING, true)) {
            return false; // FINAL/REJECTED/DRAFT → tak ada approver berikutnya
        }

        $chain = $this->resolver->resolve($doc);
        $level = (int) $doc->current_level;
        $spec = $chain[$level - 1] ?? null;
        if ($spec === null) {
            return false;
        }

        return Alerter::raiseOnce(
            "finance.approver_notify:{$doc->id}:{$level}",
            'finance.approval_pending',
            [
                'document_id' => $doc->id,
                'doc_number' => $doc->doc_number,
                'doc_type' => $doc->doc_type,
                'level' => $level,
                'approver_role' => $spec['role'],
                'approver_user_id' => $spec['user_id'],
                'recipient_user_ids' => $this->recipients($spec, $doc),
                'id_outlet' => $doc->id_outlet,
            ],
            'warning',
        );
    }

    /** @return array<int,int> user yang harus diberi tahu (pinned user, atau pemegang role ber-akses) */
    private function recipients(array $spec, FinancialDocument $doc): array
    {
        if ($spec['user_id'] !== null) {
            return [(int) $spec['user_id']];
        }
        if ($spec['role'] === null) {
            return [];
        }

        return User::role($spec['role'])->get()
            ->filter(fn (User $u) => $doc->id_outlet === null ? $u->canAccessAllOutlets() : $u->canAccessOutlet((int) $doc->id_outlet))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
