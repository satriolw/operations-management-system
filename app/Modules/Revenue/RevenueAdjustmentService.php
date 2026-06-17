<?php

namespace App\Modules\Revenue;

use App\Models\ReportRun;
use App\Models\RevenueAdjustment;
use App\Modules\Revenue\DTO\Correction;
use App\Modules\Revenue\DTO\RestateSummary;
use Illuminate\Support\Facades\DB;

/**
 * Restate revenue per tanggal nota & persist revenue_adjustments (OPS-402, PRD §8.4).
 * Mencakup VOID(unpaid) & REFUND(paid). Revenue baru = total_sales lama (report_run nota) − Σ koreksi.
 * Idempoten: updateOrCreate per (id_outlet, transaction_number) → re-run tak menggandakan.
 */
final class RevenueAdjustmentService
{
    public function __construct(
        private readonly RevenueAdjustmentDetector $detector,
        private readonly RevenueReconciler $reconciler,
    ) {}

    public function process(int $idOutlet, string $today): RestateSummary
    {
        $corrections = $this->detector->detect($idOutlet, $today);

        return DB::transaction(function () use ($idOutlet, $corrections) {
            $corrections->each(fn (Correction $c) => $this->persist($idOutlet, $c));

            $byDate = [];
            foreach ($corrections->groupBy(fn (Correction $c) => $c->notaDate) as $date => $items) {
                $correctionTotal = (int) $items->sum(fn (Correction $c) => $c->amount);
                $old = ReportRun::query()->where('id_outlet', $idOutlet)->where('report_date', $date)->value('total_sales');
                $old = $old !== null ? (int) $old : null;

                $byDate[$date] = [
                    'old' => $old,
                    'correction' => $correctionTotal,
                    'new' => $old !== null ? $old - $correctionTotal : null,
                    'count' => $items->count(),
                    'previously_reported' => $this->reconciler->wasReported($idOutlet, $date), // OPS-404
                ];
            }

            return new RestateSummary($byDate, (int) $corrections->sum(fn (Correction $c) => $c->amount));
        });
    }

    private function persist(int $idOutlet, Correction $c): void
    {
        $runId = ReportRun::query()
            ->where('id_outlet', $idOutlet)->where('report_date', $c->notaDate)->value('id');

        RevenueAdjustment::updateOrCreate(
            ['id_outlet' => $idOutlet, 'transaction_number' => $c->transactionNumber],
            [
                'report_run_id' => $runId,
                'type' => $c->type,
                'amount' => $c->amount,
                'reason' => $c->reason,
                'nota_date' => $c->notaDate,
                'approved_at' => $c->approvedDate,
                'restated_for_date' => $c->notaDate,
            ],
        );
    }
}
