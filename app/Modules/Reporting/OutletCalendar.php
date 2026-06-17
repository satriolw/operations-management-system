<?php

namespace App\Modules\Reporting;

use App\Models\OutletHoliday;
use App\Models\OutletOperatingHour;
use App\Support\Time\Wib;

/**
 * Apakah outlet tutup/libur pada tanggal tertentu (OPS-1001/OPS-106). Dipakai untuk membedakan
 * "buka tapi nol transaksi" (tetap kirim) vs "tutup/libur" (suppress). Weekday WIB: 0=Minggu..6=Sabtu.
 */
final class OutletCalendar
{
    public function isClosed(int $idOutlet, string $date): bool
    {
        // Hari libur khusus.
        $holiday = OutletHoliday::query()
            ->where('id_outlet', $idOutlet)
            ->whereDate('holiday_date', $date)
            ->exists();
        if ($holiday) {
            return true;
        }

        // Jam operasional weekday: tutup hanya bila baris ada & is_closed (unconfigured → anggap buka).
        $weekday = Wib::parse($date)->dayOfWeek; // 0=Minggu..6=Sabtu
        $hours = OutletOperatingHour::query()
            ->where('id_outlet', $idOutlet)->where('weekday', $weekday)->first();

        return $hours !== null && $hours->is_closed;
    }
}
