<?php

namespace App\Modules\Reporting;

use App\Models\ReportRun;
use Illuminate\Support\Str;

/**
 * Catatan naratif dinamis (OPS-205): kalimat CATATAN otomatis dibanding rata-rata bulan berjalan.
 * Di atas rata-rata → positif; di bawah → netral-JUJUR (tidak dibesar-besarkan). Token {{catatan}}.
 */
final class NarrativeBuilder
{
    public function forOutlet(int $idOutlet, string $date, int $total): string
    {
        $month = Str::substr($date, 0, 7); // YYYY-MM
        $avg = ReportRun::query()
            ->where('id_outlet', $idOutlet)
            ->where('report_date', 'like', $month.'%')
            ->where('report_date', '!=', $date)
            ->avg('total_sales');

        return $this->build($total, $avg !== null ? (float) $avg : null);
    }

    public function build(int $total, ?float $monthlyAvg): string
    {
        if ($monthlyAvg === null || $monthlyAvg <= 0) {
            return 'Catatan: data pembanding bulan ini belum cukup.';
        }
        if ($total > $monthlyAvg) {
            return 'Catatan: penjualan hari ini di atas rata-rata bulan ini — pertahankan momentum.';
        }
        if ($total < $monthlyAvg) {
            return 'Catatan: penjualan hari ini di bawah rata-rata bulan ini.'; // jujur, tanpa klaim berlebih
        }

        return 'Catatan: penjualan hari ini setara rata-rata bulan ini.';
    }
}
