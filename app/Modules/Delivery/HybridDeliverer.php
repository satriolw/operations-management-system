<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\Contracts\Deliverer;
use App\Support\Idempotency\IdempotencyKey;

/**
 * Mode hybrid (System Design §3.8): app kirim draft ke Head Store untuk paste manual ke grup.
 * Belum "terkirim ke investor" sampai Head Store konfirmasi (OPS-302). Idempoten per
 * (report_run, channel) — tepat satu channel aktif per hari.
 */
final class HybridDeliverer implements Deliverer
{
    public const CHANNEL = 'hybrid';

    public function mode(): string
    {
        return self::CHANNEL;
    }

    public function deliver(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        return ReportDelivery::firstOrCreate(
            ['report_run_id' => $run->id, 'channel' => self::CHANNEL],
            [
                'id_outlet' => $run->id_outlet,
                'target' => $target->investor_label,             // label, bukan PII
                'status' => 'awaiting_confirmation',             // draft ke Head Store; tunggu "Sudah saya kirim" (OPS-302)
                'idempotency_key' => IdempotencyKey::delivery($run->id, self::CHANNEL),
            ],
        );
    }
}
