<?php

namespace App\Modules\Reporting\Jobs;

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Support\Observability\JobTelemetry;
use App\Support\Observability\Metrics;
use App\Support\Time\Wib;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generate laporan harian satu outlet (OPS-104 — STUB; agregasi/render nyata = OPS-201/206).
 *
 * Idempoten (aturan emas #5): satu report_run per (outlet, report_date) via
 * unique(id_outlet, report_date). Re-run/replay tidak menghasilkan efek ganda.
 * Async via queue (Redis bila ada, fallback database). tries=3 + backoff; gagal final → failed_jobs.
 */
class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> backoff detik (System Design §3.6) */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $idOutlet,
        public readonly ?string $reportDate = null, // null → tanggal hari ini (WIB)
    ) {}

    /** Cegah eksekusi tumpang tindih untuk outlet yang sama (System Design §3.5). */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('oms:daily-report:'.$this->idOutlet))
                ->releaseAfter(60)
                ->expireAfter(900),
        ];
    }

    public function handle(): void
    {
        $date = $this->reportDate ?? Wib::normalize(now())->format('Y-m-d');

        JobTelemetry::run('reporting.generate', [
            'id_outlet' => $this->idOutlet,
            'report_date' => $date,
        ], function () use ($date) {
            // OPS-105: laporan hanya jalan untuk outlet AKTIF & terdaftar. Scheduler sudah
            // memfilter, tapi job bisa dipanggil langsung (replay/backfill) → guard di sini.
            $outlet = Outlet::find($this->idOutlet);
            if ($outlet === null || ! $outlet->active) {
                Log::channel('oms')->warning('reporting.skipped_inactive_outlet', [
                    'id_outlet' => $this->idOutlet,
                    'report_date' => $date,
                    'reason' => $outlet === null ? 'not_found' : 'inactive',
                ]);

                return; // tanpa membuat report_run / efek apa pun
            }

            // Idempotency: kunci (outlet, report_date). firstOrCreate + unique index → satu baris.
            $run = DB::transaction(function () use ($date) {
                return ReportRun::query()->firstOrCreate(
                    ['id_outlet' => $this->idOutlet, 'report_date' => $date],
                    ['status' => 'pending'],
                );
            });

            // Sudah diproses (bukan baru & bukan pending) → idempotent skip, tanpa efek ganda.
            if (! $run->wasRecentlyCreated && $run->status !== 'pending') {
                return;
            }

            // --- STUB generasi (OPS-201/206 mengisi angka & render sebenarnya) ---
            $run->update(['status' => 'generated']);
            Metrics::increment(Metrics::REPORTS_GENERATED);
        });
    }
}
