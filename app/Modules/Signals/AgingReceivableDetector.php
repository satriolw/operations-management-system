<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use App\Support\Time\Wib;
use Illuminate\Support\Collection;

/**
 * Aging piutang (OPS-605): order UNPAID melewati X hari, belum dibayar & belum di-void.
 * Ambang umur configurable (config/signals.php). Sinyal AGING_PIUTANG severity rendah (digest);
 * tersimpan di signal_event (ber-id_outlet, queryable LBE). Idempoten per transaction_number.
 */
final class AgingReceivableDetector
{
    private const LOOKBACK_DAYS = 90;

    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
    ) {}

    /** @return Collection<int, SignalEvent> */
    public function scan(int $idOutlet, string $today, ?int $agingDays = null): Collection
    {
        $agingDays ??= (int) config('signals.aging_days', 14);
        $cutoff = Wib::parse($today)->subDays($agingDays)->format('Y-m-d');
        $range = new DateRange(Wib::parse($today)->subDays(self::LOOKBACK_DAYS)->format('Y-m-d'), $today);

        return $this->parser->collection($this->source->unpaid($idOutlet, $range))
            ->filter(fn (TransactionDTO $t) => $t->isUnpaid() && ! $t->isVoid() && ! $t->isRefund())
            ->filter(fn (TransactionDTO $t) => $t->notaDate() !== null && $t->notaDate() < $cutoff)
            ->map(fn (TransactionDTO $t) => $this->record($idOutlet, $t, $today))
            ->values();
    }

    private function record(int $idOutlet, TransactionDTO $t, string $today): SignalEvent
    {
        $ageDays = (int) Wib::parse($t->notaDate())->diffInDays(Wib::parse($today));

        return SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'AGING_PIUTANG', 'ref_transaction_number' => $t->transactionNumber],
            [
                'severity' => 'low', // informasional → digest (OPS-1002)
                'status' => 'OPEN',
                'detected_at' => now(),
                'payload_json' => [
                    'amount' => $t->grandTotal,
                    'nota_date' => $t->notaDate(),
                    'age_days' => $ageDays,
                ],
            ],
        );
    }
}
