<?php

namespace App\Modules\Signals;

use App\Models\CashierInputScore;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use Illuminate\Support\Collection;

/**
 * KPI akurasi input per kasir (OPS-603). Rate void/refund berkategori "salah input".
 * ATRIBUSI ke id_cashier (pembuat nota), BUKAN refund_void_by (yang mengoreksi).
 * Hasil disimpan ke cashier_input_scores (unik per outlet+cashier+periode).
 */
final class CashierInputScorer
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
        private readonly ReasonClassifier $classifier,
    ) {}

    /** @return Collection<int, CashierInputScore> */
    public function scan(int $idOutlet, DateRange $range, string $period): Collection
    {
        return $this->parser->collection($this->source->voidRefunds($idOutlet, $range))
            ->filter(fn (TransactionDTO $t) => $t->idCashier !== null)
            ->groupBy(fn (TransactionDTO $t) => $t->idCashier) // atribusi ke pembuat nota
            ->map(fn (Collection $items, $idCashier) => $this->score($idOutlet, (int) $idCashier, $period, $items))
            ->values();
    }

    private function score(int $idOutlet, int $idCashier, string $period, Collection $items): CashierInputScore
    {
        $total = $items->count();
        $errors = $items->filter(fn (TransactionDTO $t) => $this->classifier->isInputError($t->reason))->count();

        return CashierInputScore::updateOrCreate(
            ['id_outlet' => $idOutlet, 'id_cashier' => $idCashier, 'period' => $period],
            [
                'error_count' => $errors,
                'txn_count' => $total,
                'rate' => $total > 0 ? round($errors / $total, 4) : 0,
            ],
        );
    }
}
