<?php

namespace App\Modules\Reporting;

/**
 * Tentukan aksi laporan harian (OPS-1001): tutup/libur → suppress; buka-nol → empty-state
 * (tetap kirim + catatan); selain itu normal.
 */
final class ReportDecider
{
    public function __construct(private readonly OutletCalendar $calendar) {}

    public function decide(int $idOutlet, string $date, int $txnCount): ReportDecision
    {
        if ($this->calendar->isClosed($idOutlet, $date)) {
            return new ReportDecision(ReportDecision::SUPPRESS, 'Outlet tutup/libur — laporan disuppress.');
        }

        if ($txnCount <= 0) {
            return new ReportDecision(
                ReportDecision::EMPTY_STATE,
                'Catatan: belum ada transaksi tercatat pada hari ini.',
            );
        }

        return new ReportDecision(ReportDecision::NORMAL);
    }
}
