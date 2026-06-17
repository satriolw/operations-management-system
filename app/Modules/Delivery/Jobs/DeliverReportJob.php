<?php

namespace App\Modules\Delivery\Jobs;

use App\Models\DeliveryTarget;
use App\Models\ReportRun;
use App\Modules\Delivery\DeliveryDispatcher;
use App\Support\Observability\Alerter;
use App\Support\Observability\JobTelemetry;
use App\Support\Observability\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Kirim laporan ke satu target (OPS-305). Async, retry bertingkat; status terkirim/gagal tercatat
 * di report_delivery (via DeliveryDispatcher). Gagal final → alert ke ops (bukan silent).
 */
class DeliverReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> backoff detik */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $reportRunId,
        public readonly int $deliveryTargetId,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('oms:deliver:'.$this->reportRunId.':'.$this->deliveryTargetId))->releaseAfter(60)];
    }

    public function handle(DeliveryDispatcher $dispatcher): void
    {
        $run = ReportRun::find($this->reportRunId);
        $target = DeliveryTarget::find($this->deliveryTargetId);
        if ($run === null || $target === null) {
            return; // tak ada yang dikirim
        }

        JobTelemetry::run('delivery.deliver', [
            'id_outlet' => $run->id_outlet,
            'report_run_id' => $run->id,
        ], function () use ($dispatcher, $run, $target) {
            $dispatcher->dispatch($run, $target);   // catat report_delivery (idempoten, fallback hybrid)
            Metrics::increment(Metrics::REPORTS_DELIVERED);
        });
    }

    /** Gagal setelah semua percobaan → alert ops (System Design §3.6). */
    public function failed(Throwable $e): void
    {
        Metrics::increment(Metrics::REPORTS_FAILED);
        Alerter::raise('delivery.failed', [
            'report_run_id' => $this->reportRunId,
            'delivery_target_id' => $this->deliveryTargetId,
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
