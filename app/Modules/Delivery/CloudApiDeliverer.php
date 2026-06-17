<?php

namespace App\Modules\Delivery;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\Contracts\Deliverer;
use App\Modules\Delivery\Exceptions\DeliveryFailed;

/**
 * Mode assisted/full_auto via WhatsApp Cloud API (Groups) — System Design §3.8.
 * Terblokir OBA (dependensi non-teknis); implementasi penuh = OPS-303. Untuk sekarang menolak
 * (DeliveryFailed) → DeliveryDispatcher fallback otomatis ke hybrid. Tak ada kiriman diam-diam.
 */
final class CloudApiDeliverer implements Deliverer
{
    public function mode(): string
    {
        return 'assisted'; // menangani assisted & full_auto
    }

    public function deliver(ReportRun $run, DeliveryTarget $target): ReportDelivery
    {
        // Groups API belum aktif (OBA pending, OPS-303/306). Jangan kirim diam-diam → fallback.
        throw new DeliveryFailed('Cloud API (Groups) belum aktif — menunggu OBA (OPS-303).');
    }
}
