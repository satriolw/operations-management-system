<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Modules\Delivery\Exceptions\DeliveryFailed;
use Illuminate\Support\Facades\Log;

/**
 * "Setujui & Kirim" assisted (OPS-304). Head Store menyetujui draft → app mengirim via Cloud API
 * (CloudApiDeliverer). Bila Cloud API gagal (precondition/HTTP) → fallback hybrid (paste manual),
 * tak ada kegagalan diam-diam. Idempoten lewat CloudApiDeliverer (draft sama → SENT).
 */
final class AssistedApproval
{
    public function __construct(
        private readonly CloudApiDeliverer $cloud,
        private readonly HybridDeliverer $hybrid,
    ) {}

    public function approveAndSend(ReportDelivery $delivery, DeliveryTarget $target): ReportDelivery
    {
        $run = $delivery->reportRun;

        try {
            return $this->cloud->deliver($run, $target); // updateOrCreate record cloud_api → SENT
        } catch (DeliveryFailed $e) {
            // Tandai upaya assisted gagal + fallback hybrid (Head Store paste manual).
            $delivery->update(['status' => ReportDelivery::FAILED, 'error' => $e->getMessage()]);
            Log::channel('oms')->warning('delivery.assisted_fallback_hybrid', [
                'report_run_id' => $run->id, 'id_outlet' => $run->id_outlet, 'reason' => $e->getMessage(),
            ]);

            return $this->hybrid->deliver($run, $target);
        }
    }
}
