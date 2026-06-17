<?php

namespace App\Modules\Revenue;

use App\Models\ReportDelivery;
use App\Models\ReportRun;

/**
 * Rekonsiliasi dgn laporan terkirim (OPS-404): apakah tanggal yang di-restate SUDAH pernah
 * dilaporkan ke investor? Bila ya, narasi koreksi jelas ("koreksi atas laporan tanggal X").
 * "Sudah dilaporkan" = ada report_run terkonfirmasi terkirim (confirmed_sent/sent) atau status delivered.
 */
final class RevenueReconciler
{
    public function wasReported(int $idOutlet, string $date): bool
    {
        $run = ReportRun::query()->where('id_outlet', $idOutlet)->where('report_date', $date)->first();
        if ($run === null) {
            return false;
        }
        if ($run->status === 'delivered') {
            return true;
        }

        return $run->deliveries()
            ->whereIn('status', [ReportDelivery::CONFIRMED_SENT, ReportDelivery::SENT])
            ->exists();
    }
}
