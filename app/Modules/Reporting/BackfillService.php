<?php

namespace App\Modules\Reporting;

/**
 * Backfill/replay laporan untuk tanggal lampau (OPS-703). Replay IDEMPOTEN (ReportComposer
 * updateOrCreate per (outlet, report_date) → tak ada kiriman/baris ganda). Mode dry-run:
 * bangun preview teks TANPA persist/kirim.
 */
final class BackfillService
{
    public function __construct(
        private readonly ReportComposer $composer,
        private readonly DailyDashboardAggregator $aggregator,
        private readonly RevenueSplitter $splitter,
        private readonly ReportMessageBuilder $message,
    ) {}

    /**
     * @param  array{nama_outlet?:string,nama_investor?:string}  $context
     * @return array{dry_run:bool,persisted:bool,report_run_id?:?int,text?:string}
     */
    public function run(int $idOutlet, string $date, bool $dryRun = false, array $context = []): array
    {
        if ($dryRun) {
            $metrics = $this->aggregator->forOutlet($idOutlet, $date);
            $split = $this->splitter->forOutlet($idOutlet, $date, $metrics->totalSales);

            return [
                'dry_run' => true,
                'persisted' => false,
                'text' => $this->message->build($idOutlet, $date, $metrics, $split, $context),
            ];
        }

        $run = $this->composer->compose($idOutlet, $date, $context); // idempoten; null bila tutup/libur

        return ['dry_run' => false, 'persisted' => $run !== null, 'report_run_id' => $run?->id];
    }
}
