<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\Exceptions\DeliveryFailed;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Support\Facades\Log;

/**
 * Pilih transport per target & kirim (System Design §3.8). Idempotency: tepat satu channel
 * aktif per (report_run, channel) per hari.
 *
 *  - hybrid    → draft ke Head Store untuk paste manual (OPS-302).
 *  - assisted  → siapkan draft, TUNGGU Head Store tekan "Setujui & Kirim" (OPS-304) — app TIDAK
 *                auto-kirim. Approval memanggil CloudApiDeliverer lewat AssistedApproval.
 *  - full_auto → kirim otomatis via Cloud API; gagal → fallback hybrid + alert (tak diam-diam).
 *
 * effectiveMode() menurunkan assisted/full_auto ke hybrid bila OBA tak siap / nomor lost (OPS-804).
 */
final class DeliveryDispatcher
{
    public function __construct(
        private readonly HybridDeliverer $hybrid,
        private readonly CloudApiDeliverer $cloud,
    ) {}

    public function dispatch(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        $mode = $target->effectiveMode();

        if ($mode === HybridDeliverer::CHANNEL) {
            return $this->hybrid->deliver($run, $target);
        }

        if ($mode === 'assisted') {
            return $this->prepareAssisted($run, $target); // tunggu approval; tak kirim
        }

        // full_auto → Cloud API; gagal → fallback hybrid + alert.
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

    /**
     * Siapkan draft assisted (OPS-304): satu record cloud_api berstatus awaiting_approval. Idempoten
     * — re-run tak menggandakan & tak menimpa yang sudah terkirim. Pengiriman terjadi saat Head Store
     * menekan "Setujui & Kirim" (AssistedApproval), bukan di sini.
     */
    private function prepareAssisted(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        return ReportDelivery::firstOrCreate(
            ['report_run_id' => $run->id, 'channel' => CloudApiDeliverer::CHANNEL],
            [
                'id_outlet' => $run->id_outlet,
                'target' => $target->investor_label,
                'status' => ReportDelivery::AWAITING_APPROVAL,
                'idempotency_key' => IdempotencyKey::delivery($run->id, CloudApiDeliverer::CHANNEL),
            ],
        );
    }
}
