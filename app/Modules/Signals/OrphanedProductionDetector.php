<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use App\Modules\Signals\Contracts\ReplacementMatcher;
use Illuminate\Support\Collection;

/**
 * Flag orphaned production (OPS-604): void/refund pada order progress_percentage > 0 tanpa nota
 * pengganti → kemungkinan kebocoran produksi. Pakai ReplacementMatcher (heuristik/abstraksi; field
 * NEVIRA belum ada). Label "perlu ditinjau" (BUKAN tuduhan). Uji kasus 6003 (produksi 100% lalu void).
 */
final class OrphanedProductionDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
        private readonly ReplacementMatcher $matcher,
    ) {}

    /** @return Collection<int, SignalEvent> */
    public function scan(int $idOutlet, DateRange $range): Collection
    {
        return $this->parser->collection($this->source->voidRefunds($idOutlet, $range))
            ->filter(fn (TransactionDTO $t) => $t->progressPercentage > 0)
            ->reject(fn (TransactionDTO $t) => $this->matcher->hasReplacement($t)) // ada pengganti → bukan orphaned
            ->map(fn (TransactionDTO $t) => $this->record($idOutlet, $t))
            ->values();
    }

    private function record(int $idOutlet, TransactionDTO $t): SignalEvent
    {
        return SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'ORPHANED_PRODUCTION', 'ref_transaction_number' => $t->transactionNumber],
            [
                'severity' => 'high',
                'id_cashier' => $t->idCashier,
                'status' => 'OPEN',
                'detected_at' => $t->approvedAt ?? now(),
                'payload_json' => [
                    'progress_percentage' => $t->progressPercentage,
                    'amount' => $t->grandTotal,
                    'reason' => $t->reason,
                    'label' => 'needs_review', // perlu ditinjau, bukan kebocoran terkonfirmasi
                ],
            ],
        );
    }
}
