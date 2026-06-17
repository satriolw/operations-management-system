<?php

namespace App\Modules\Delivery;

use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Support\Observability\Alerter;

/**
 * Watchdog anti silent-failure (OPS-704, System Design §3.6). Tiap outlet aktif WAJIB punya
 * report_delivery TERKONFIRMASI (confirmed_sent/sent — bukan draft awaiting_confirmation) pada
 * tanggalnya; bila tidak → alert "laporan tidak terkirim". Kegagalan terdeteksi, bukan diam.
 */
final class DeliveryWatchdog
{
    /**
     * @return array<int,int> id_outlet yang TIDAK punya pengiriman terkonfirmasi pada $date
     */
    public function check(string $date): array
    {
        $missing = [];

        foreach (Outlet::query()->where('active', true)->get() as $outlet) {
            if (! $this->hasConfirmedDelivery((int) $outlet->id_outlet, $date)) {
                $missing[] = (int) $outlet->id_outlet;
                Alerter::raise('report.not_delivered', [
                    'id_outlet' => (int) $outlet->id_outlet,
                    'report_date' => $date,
                ]);
            }
        }

        return $missing;
    }

    private function hasConfirmedDelivery(int $idOutlet, string $date): bool
    {
        $run = ReportRun::query()->where('id_outlet', $idOutlet)->where('report_date', $date)->first();
        if ($run === null) {
            return false;
        }

        // Status KONFIRMASI (OPS-302), bukan draft 'awaiting_confirmation'.
        return $run->deliveries()
            ->whereIn('status', [ReportDelivery::CONFIRMED_SENT, ReportDelivery::SENT])
            ->exists();
    }
}
