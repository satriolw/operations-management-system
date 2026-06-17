<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use App\Modules\Identity\RoleLevelMap;
use Illuminate\Support\Collection;

/**
 * Flag self-approval — SADAR KEBIJAKAN (OPS-601, CLAUDE.md).
 *
 * Self-approval = refund_void_by == refund_void_approved_by. PELANGGARAN hanya bila level role
 * penyetuju < Kepala Toko (RoleLevelMap.allowsDualAuthority=false). Bila >= Kepala Toko → pengecualian
 * SAH (audit, severity rendah). Bila id_role belum dipetakan (null) → "perlu ditinjau" (jangan blokir).
 *
 * Catatan: role penyetuju memakai cashier.id_role sbg proxy (refund_void_approved_by == cashier saat
 * self-approval). Idempoten per (outlet, transaction_number). payload tanpa PII customer.
 */
final class SelfApprovalDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
        private readonly RoleLevelMap $roles,
    ) {}

    /** @return Collection<int, SignalEvent> */
    public function scan(int $idOutlet, DateRange $range): Collection
    {
        return $this->parser->collection($this->source->voidRefunds($idOutlet, $range))
            ->filter(fn (TransactionDTO $t) => $t->isSelfApproval())
            ->map(fn (TransactionDTO $t) => $this->record($idOutlet, $t))
            ->values();
    }

    private function record(int $idOutlet, TransactionDTO $t): SignalEvent
    {
        $allowed = $this->roles->allowsDualAuthority($t->idRole);

        [$severity, $outcome] = match ($allowed) {
            true => ['low', 'legitimate'],   // >= Kepala Toko → pengecualian sah (digest)
            false => ['high', 'violation'],  // < Kepala Toko → pelanggaran (real-time)
            null => ['high', 'needs_review'], // role belum dipetakan → perlu ditinjau
        };

        return SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'SELF_APPROVAL', 'ref_transaction_number' => $t->transactionNumber],
            [
                'severity' => $severity,
                'id_cashier' => $t->idCashier,
                'status' => 'OPEN',
                'detected_at' => $t->approvedAt ?? now(),
                'payload_json' => [ // metrik integritas, tanpa PII customer
                    'amount' => $t->grandTotal,
                    'reason' => $t->reason,
                    'role_id' => $t->idRole,
                    'requested_by' => $t->refundVoidBy,
                    'approved_by' => $t->refundVoidApprovedBy,
                    'outcome' => $outcome,
                ],
            ],
        );
    }
}
