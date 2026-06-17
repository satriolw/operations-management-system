<?php

namespace App\Modules\Reporting;

use App\Models\ReportRun;
use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Support\Observability\Alerter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rakit laporan final (OPS-206): teks (OPS-203) + blok Penyesuaian Revenue opsional (OPS-401) +
 * gambar dashboard (OPS-204) → simpan ke report_run. Idempoten per (outlet, report_date).
 * Toleran gambar: bila render PNG gagal (Chromium absen), laporan teks tetap tersimpan/terkirim (R2).
 * Empty-state/hari tutup (OPS-1001): tutup/libur → suppress (null); buka-nol → tetap kirim + catatan + alert.
 */
final class ReportComposer
{
    public function __construct(
        private readonly DailyDashboardAggregator $aggregator,
        private readonly RevenueSplitter $splitter,
        private readonly ReportMessageBuilder $message,
        private readonly DashboardImageRenderer $image,
        private readonly ReportDecider $decider,
    ) {}

    /**
     * @param  array{nama_outlet?:string,nama_investor?:string}  $context
     * @param  ?string  $adjustmentText  blok Penyesuaian Revenue (dari OPS-403) — null bila tak ada koreksi
     * @return ?ReportRun  null bila outlet tutup/libur (disuppress, OPS-1001)
     */
    public function compose(int $idOutlet, string $date, array $context = [], ?string $adjustmentText = null): ?ReportRun
    {
        $metrics = $this->aggregator->forOutlet($idOutlet, $date);
        $split = $this->splitter->forOutlet($idOutlet, $date, $metrics->totalSales);

        // OPS-1001: tutup/libur → suppress; buka-nol → tetap kirim dgn catatan + alert internal.
        $decision = $this->decider->decide($idOutlet, $date, $metrics->txnCount);
        if ($decision->shouldSuppress()) {
            Log::channel('oms')->info('report.suppressed_closed', ['id_outlet' => $idOutlet, 'report_date' => $date]);

            return null;
        }
        if ($decision->isEmptyState()) {
            Alerter::raise('outlet.silent_zero', ['id_outlet' => $idOutlet, 'report_date' => $date], 'warning');
        }

        $text = $this->message->build($idOutlet, $date, $metrics, $split, $context + [
            'penyesuaian_revenue' => $adjustmentText, // tampil hanya bila ada koreksi
        ]);

        // Buka-nol: sisipkan catatan jujur di atas laporan.
        if ($decision->isEmptyState() && $decision->note !== null) {
            $text = $decision->note."\n\n".$text;
        }

        $imagePath = $this->renderImageSafely($metrics, $split, $date, $context, $idOutlet);

        // Idempoten: satu report_run per (outlet, report_date). Status 'generated' = siap di-preview/kirim.
        return ReportRun::updateOrCreate(
            ['id_outlet' => $idOutlet, 'report_date' => $date],
            [
                'status' => 'generated',
                'payload_text' => $text,
                'image_path' => $imagePath,
                'total_sales' => $metrics->totalSales,
                'realized' => $split->realized,
                'receivable' => $split->receivable,
                'txn_count' => $metrics->txnCount,
            ],
        );
    }

    /** Gambar opsional: gagal render tidak boleh menggagalkan laporan teks. */
    private function renderImageSafely($metrics, $split, string $date, array $context, int $idOutlet): ?string
    {
        $path = storage_path("app/reports/{$idOutlet}-{$date}.png");

        try {
            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0775, true);
            }

            return $this->image->render($metrics, $split, $date, $context, $path);
        } catch (Throwable $e) {
            Log::channel('oms')->warning('report.image_render_failed', [
                'id_outlet' => $idOutlet, 'report_date' => $date, 'reason' => $e->getMessage(),
            ]);

            return null; // laporan teks tetap jalan
        }
    }
}
