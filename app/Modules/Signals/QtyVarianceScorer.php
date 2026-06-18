<?php

namespace App\Modules\Signals;

use App\Models\CashierInputScore;
use App\Models\TransactionAuditConfig;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\AuditTransaction;
use App\Modules\Ingestion\DTO\DateRange;
use Illuminate\Support\Collection;

/**
 * Variance quantity vs actual_quantity → perkuat KPI akurasi input (OPS-1405, Epic N §3.17(d)).
 * BUKAN sinyal/alert per-kejadian — agregat per kasir (atribusi id_cashier pembuat) ke
 * cashier_input_scores.qty_variance_count (menyatu Epic F/OPS-603). Ambang qty_variance_pct
 * configurable (kalibrasi minimal-order; semantik belum dikonfirmasi → konservatif).
 */
final class QtyVarianceScorer
{
    public function __construct(private readonly TransactionSource $source) {}

    /** @return Collection<int,CashierInputScore> */
    public function scan(int $idOutlet, DateRange $range, string $period): Collection
    {
        $threshold = (float) TransactionAuditConfig::forOutlet($idOutlet)->qty_variance_pct;

        $counts = [];
        foreach ($this->source->dailyTransactions($idOutlet, $range) as $row) {
            $t = AuditTransaction::fromRow($row);
            $cashier = $t->idCashier();
            if ($cashier === null) {
                continue; // atribusi ke pembuat nota
            }
            foreach ($t->services() as $s) {
                if ($s['actual_quantity'] === null || $s['quantity'] <= 0) {
                    continue;
                }
                $pct = abs($s['quantity'] - $s['actual_quantity']) / $s['quantity'] * 100;
                if ($pct > $threshold) {
                    $counts[$cashier] = ($counts[$cashier] ?? 0) + 1;
                }
            }
        }

        return collect($counts)->map(fn (int $n, $cashier) => CashierInputScore::updateOrCreate(
            ['id_outlet' => $idOutlet, 'id_cashier' => (int) $cashier, 'period' => $period],
            ['qty_variance_count' => $n], // hanya kolom ini — tak menimpa error/txn dari OPS-603
        ))->values();
    }
}
