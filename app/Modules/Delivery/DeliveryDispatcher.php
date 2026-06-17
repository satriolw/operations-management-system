<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\Exceptions\DeliveryFailed;
use Illuminate\Support\Facades\Log;

/**
 * Pilih transport per target & kirim (System Design §3.8). Idempotency: tepat satu channel
 * aktif per (report_run, channel) per hari. Fallback otomatis ke hybrid bila Cloud API gagal —
 * tidak ada kegagalan diam-diam.
 */
final class DeliveryDispatcher
{
    public function __construct(
        private readonly HybridDeliverer $hybrid,
        private readonly CloudApiDeliverer $cloud,
    ) {}

    public function dispatch(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        // effectiveMode() sudah turun ke hybrid bila OBA tak siap / nomor lost (OPS-804).
        $mode = $target->effectiveMode();

        if ($mode === HybridDeliverer::CHANNEL) {
            return $this->hybrid->deliver($run, $target);
        }

        // assisted / full_auto → Cloud API; gagal → fallback hybrid + alert.
        try {
            return $this->cloud->deliver($run, $target);
        } catch (DeliveryFailed $e) {
            Log::channel('oms')->warning('delivery.fallback_hybrid', [
                'report_run_id' => $run->id, 'id_outlet' => $run->id_outlet,
                'mode' => $mode, 'reason' => $e->getMessage(),
            ]);

            return $this->hybrid->deliver($run, $target);
        }
    }
}
