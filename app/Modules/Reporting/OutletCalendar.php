<?php

namespace App\Modules\Reporting;

use App\Models\OutletHoliday;
use App\Models\OutletOperatingHour;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;

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

    /**
     * Outlet BUKA pada jam-dinding $now (WIB) — untuk adaptive polling (OPS-109): poller sering
     * skip outlet yang sedang tutup. Buka = hari tak libur/tak is_closed DAN jam ∈ [open, close].
     * Jam tak terkonfigurasi → anggap buka penuh (selaras isClosed/BusinessHoursClock OPS-106).
     */
    public function isOpenNow(int $idOutlet, CarbonInterface $now): bool
    {
        $now = Wib::normalize($now);
        if ($this->isClosed($idOutlet, $now->toDateString())) {
            return false;
        }

        $row = OutletOperatingHour::query()
            ->where('id_outlet', $idOutlet)->where('weekday', $now->dayOfWeek)->first();

        // Tak ada baris / jam tak diisi → buka penuh.
        if ($row === null || ! $row->open_time || ! $row->close_time) {
            return true;
        }

        $open = $now->copy()->setTimeFromTimeString(substr((string) $row->open_time, 0, 8));
        $close = $now->copy()->setTimeFromTimeString(substr((string) $row->close_time, 0, 8));

        return $now->betweenIncluded($open, $close);
    }
}
