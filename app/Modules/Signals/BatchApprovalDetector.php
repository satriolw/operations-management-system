<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use Illuminate\Support\Collection;

/**
 * Flag batch-approval (OPS-602): > N persetujuan oleh approver yang sama pada menit yang sama
 * (ambang configurable, default 2 → 3+ memicu). Idempoten per (approver, menit). payload tanpa PII.
 * Uji kasus user 180 (4 void @ 9 Juni 11:58).
 */
final class BatchApprovalDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
    ) {}

    /** @return Collection<int, SignalEvent> */
    public function scan(int $idOutlet, DateRange $range, ?int $threshold = null): Collection
    {
        $threshold ??= (int) config('signals.batch_threshold', 2);

        return $this->parser->collection($this->source->voidRefunds($idOutlet, $range))
            ->filter(fn (TransactionDTO $t) => $t->approvedAt !== null && $t->refundVoidApprovedBy !== null)
            ->groupBy(fn (TransactionDTO $t) => $t->refundVoidApprovedBy.'|'.$t->approvedAt->format('Y-m-d H:i'))
            ->filter(fn (Collection $items) => $items->count() > $threshold)
            ->map(fn (Collection $items, string $key) => $this->record($idOutlet, $key, $items))
            ->values();
    }

    private function record(int $idOutlet, string $key, Collection $items): SignalEvent
    {
        [$approver, $bucket] = explode('|', $key);

        return SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'BATCH_APPROVAL', 'ref_transaction_number' => 'batch:'.$key],
            [
                'severity' => 'high',
                'status' => 'OPEN',
                'detected_at' => $items->first()->approvedAt,
                'payload_json' => [
                    'approved_by' => (int) $approver,
                    'bucket' => $bucket,
                    'count' => $items->count(),
                    'transactions' => $items->pluck('transactionNumber')->all(),
                ],
            ],
        );
    }
}
