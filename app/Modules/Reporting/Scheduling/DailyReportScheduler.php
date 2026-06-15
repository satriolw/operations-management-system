<?php

namespace App\Modules\Reporting\Scheduling;

use App\Models\Outlet;
use App\Modules\Reporting\Jobs\GenerateDailyReportJob;
use App\Support\Time\Wib;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Mendaftarkan job laporan harian PER OUTLET pada jam masing-masing, timezone Asia/Jakarta
 * (System Design §3.5). Diekstrak dari routes/console.php agar dapat diuji.
 *
 * Jadwal disebar per jam outlet (bukan serentak) → mengurangi beban NEVIRA (System Design §4.2).
 */
final class DailyReportScheduler
{
    public static function apply(Schedule $schedule): void
    {
        Outlet::query()
            ->where('active', true)
            ->whereNotNull('report_time')
            ->get()
            ->each(function (Outlet $outlet) use ($schedule) {
                $schedule->job(new GenerateDailyReportJob((int) $outlet->id_outlet))
                    ->dailyAt(self::hhmm($outlet->report_time))
                    ->timezone(Wib::TZ)
                    ->name('oms:daily-report:'.$outlet->id_outlet)
                    ->withoutOverlapping();
            });
    }

    /** Normalkan 'HH:MM:SS' / 'HH:MM' → 'HH:MM' untuk dailyAt(). */
    private static function hhmm(string $time): string
    {
        return substr($time, 0, 5);
    }
}
