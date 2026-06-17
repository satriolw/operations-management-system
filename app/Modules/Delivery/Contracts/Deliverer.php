<?php

namespace App\Modules\Delivery\Contracts;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Models\ReportRun;

/**
 * Transport pengiriman laporan (System Design §3.8). Satu interface, banyak mode
 * (hybrid/assisted/full_auto). Domain Reporting tak tahu transport — ditukar lewat konfigurasi
 * per target. Implementasi mengembalikan record report_delivery (idempoten).
 */
interface Deliverer
{
    /** Mode yang ditangani: hybrid|assisted|full_auto. */
    public function mode(): string;

    public function deliver(ReportRun $run, DeliveryTarget $target): ReportDelivery;
}
