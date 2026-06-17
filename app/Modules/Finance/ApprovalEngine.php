<?php

namespace App\Modules\Finance;

use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\User;
use App\Modules\Finance\Exceptions\ApprovalException;
use App\Support\Time\Wib;
use Illuminate\Support\Facades\DB;

/**
 * Engine alur status + approval berurutan dokumen keuangan (M2-03, System Design §4).
 *
 *   DRAFT --submit--> SUBMITTED --L1--> APPROVED_L1 --L2--> FINAL
 *      reject (level mana pun) --> REJECTED (catatan wajib)
 *
 * Rantai efektif dari ChainResolver (band + reviewer≠requester skip+geser). Tak boleh loncat level;
 * approver wajib sesuai rantai & punya akses outlet; FINAL immutable. Tiap aksi → document_approvals
 * (append-only).
 */
final class ApprovalEngine
{
    public function __construct(private readonly ChainResolver $resolver) {}

    /** DRAFT → SUBMITTED (hitung band, set level 1). Hanya dari DRAFT. */
    public function submit(FinancialDocument $doc, User $actor): FinancialDocument
    {
        if ($doc->status !== FinancialDocument::STATUS_DRAFT) {
            throw ApprovalException::invalidTransition($doc->status, 'submit');
        }

        $doc->amount_band = FinancialDocument::bandFor((float) $doc->amount);
        $this->resolver->resolve($doc); // validasi rantai ada (lempar bila tidak)

        $doc->status = FinancialDocument::STATUS_SUBMITTED;
        $doc->current_level = 1;
        $doc->save();

        return $doc;
    }

    /** Setujui level berjalan. Maju level; level terakhir → FINAL. */
    public function approve(FinancialDocument $doc, User $actor, ?string $note = null): FinancialDocument
    {
        $this->assertActionable($doc, 'approve');
        $chain = $this->resolver->resolve($doc);
        $level = (int) $doc->current_level;
        $spec = $chain[$level - 1] ?? throw ApprovalException::invalidTransition($doc->status, 'approve');

        $this->assertEligible($doc, $actor, $spec, $level);

        return DB::transaction(function () use ($doc, $actor, $note, $level, $chain) {
            DocumentApproval::create([
                'document_id' => $doc->id, 'level' => $level,
                'approver_user_id' => $actor->id, 'approver_role' => $this->actorRoleFor($actor, $chain[$level - 1]),
                'action' => DocumentApproval::APPROVED, 'note' => $note, 'acted_at' => Wib::normalize(now()),
            ]);

            if ($level >= count($chain)) {
                $doc->status = FinancialDocument::STATUS_FINAL;
                $doc->finalized_at = Wib::normalize(now());
            } else {
                $doc->status = $level === 1 ? FinancialDocument::STATUS_APPROVED_L1 : FinancialDocument::STATUS_APPROVED_L2;
                $doc->current_level = $level + 1;
            }
            $doc->save();

            return $doc;
        });
    }

    /** Tolak di level mana pun (catatan WAJIB) → REJECTED. */
    public function reject(FinancialDocument $doc, User $actor, string $note): FinancialDocument
    {
        $this->assertActionable($doc, 'reject');
        if (trim($note) === '') {
            throw ApprovalException::noteRequired();
        }

        $chain = $this->resolver->resolve($doc);
        $level = (int) $doc->current_level;
        $spec = $chain[$level - 1] ?? throw ApprovalException::invalidTransition($doc->status, 'reject');
        $this->assertEligible($doc, $actor, $spec, $level);

        return DB::transaction(function () use ($doc, $actor, $note, $level, $chain) {
            DocumentApproval::create([
                'document_id' => $doc->id, 'level' => $level,
                'approver_user_id' => $actor->id, 'approver_role' => $this->actorRoleFor($actor, $chain[$level - 1]),
                'action' => DocumentApproval::REJECTED, 'note' => $note, 'acted_at' => Wib::normalize(now()),
            ]);

            $doc->status = FinancialDocument::STATUS_REJECTED;
            $doc->save();

            return $doc;
        });
    }

    private function assertActionable(FinancialDocument $doc, string $action): void
    {
        if (in_array($doc->status, [FinancialDocument::STATUS_FINAL, FinancialDocument::STATUS_REJECTED], true)) {
            throw $doc->isFinal() ? ApprovalException::immutable() : ApprovalException::invalidTransition($doc->status, $action);
        }
        if (! in_array($doc->status, [
            FinancialDocument::STATUS_SUBMITTED, FinancialDocument::STATUS_APPROVED_L1, FinancialDocument::STATUS_APPROVED_L2,
        ], true)) {
            throw ApprovalException::invalidTransition($doc->status, $action); // DRAFT belum di-submit
        }
    }

    /** Approver sesuai rantai + reviewer≠requester + punya akses outlet dokumen. */
    private function assertEligible(FinancialDocument $doc, User $actor, array $spec, int $level): void
    {
        if ((int) $actor->id === (int) $doc->requester_user_id) {
            throw ApprovalException::reviewerIsRequester();
        }

        $matches = $spec['user_id'] !== null
            ? (int) $actor->id === (int) $spec['user_id']
            : ($spec['role'] !== null && $actor->hasRole($spec['role']));
        if (! $matches) {
            throw ApprovalException::notExpectedApprover($level);
        }

        // Scoping: dokumen outlet → approver harus berakses outlet itu; Head Office → akses-semua.
        $hasAccess = $doc->id_outlet === null
            ? $actor->canAccessAllOutlets()
            : $actor->canAccessOutlet((int) $doc->id_outlet);
        if (! $hasAccess) {
            throw ApprovalException::notExpectedApprover($level);
        }
    }

    private function actorRoleFor(User $actor, array $spec): ?string
    {
        return $spec['role'] ?? $actor->getRoleNames()->first();
    }
}
