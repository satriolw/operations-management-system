<?php

namespace App\Modules\Reporting;

use App\Support\Time\Wib;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Definisi periode laporan (OPS-1005). Periode = HARI KALENDER PENUH (WIB), dikirim setelah
 * hari ditutup (cutoff). Konsisten dgn Penyesuaian Revenue yang memakai tanggal nota WIB.
 */
final class ReportPeriod
{
    /**
     * Jendela hari kalender penuh utk satu report_date (WIB): [00:00:00, 23:59:59].
     *
     * @return array{start:CarbonImmutable,end:CarbonImmutable}
     */
    public static function dayWindow(CarbonInterface|string $date): array
    {
        $day = Wib::parse(is_string($date) ? $date : $date->format('Y-m-d'));

        return [
            'start' => $day->startOfDay(),
            'end' => $day->endOfDay(),
        ];
    }

    /** Waktu paling awal laporan boleh dikirim: cutoff pada hari report_date (WIB). */
    public static function sendAfter(CarbonInterface|string $date): CarbonImmutable
    {
        $cutoff = (string) config('reporting.cutoff_time', '23:59');

        return Wib::parse((is_string($date) ? $date : $date->format('Y-m-d')).' '.$cutoff);
    }

    public static function isCalendarDay(): bool
    {
        return config('reporting.period', 'calendar_day') === 'calendar_day';
    }
}
