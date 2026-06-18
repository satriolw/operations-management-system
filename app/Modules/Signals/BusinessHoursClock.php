<?php

namespace App\Modules\Signals;

use App\Models\OutletHoliday;
use App\Models\OutletOperatingHour;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;

/**
 * Hitung durasi JAM OPERASIONAL antara dua waktu (Epic M, System Design §3.16). Jeda jam tutup &
 * hari libur (outlet_operating_hours + outlet_holidays, OPS-803) → overdue tak banjir false-positive
 * untuk nota lintas-malam ("12 Jam" dibuat 20:47 → est 08:47 tak terlambat bila outlet tutup semalam).
 */
final class BusinessHoursClock
{
    /** Menit outlet BUKA dalam [$from, $to] (WIB). 0 bila $to ≤ $from. */
    public function operationalMinutesBetween(int $idOutlet, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = Wib::normalize($from);
        $to = Wib::normalize($to);
        if ($to->lte($from)) {
            return 0;
        }

        $hours = OutletOperatingHour::query()->where('id_outlet', $idOutlet)->get()->keyBy('weekday');
        $holidays = OutletHoliday::query()->where('id_outlet', $idOutlet)->pluck('holiday_date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->flip();

        $total = 0;
        $day = CarbonImmutable::instance($from)->startOfDay();
        $lastDay = CarbonImmutable::instance($to)->startOfDay();

        while ($day->lte($lastDay)) {
            $total += $this->openMinutesOnDay($day, $hours, $holidays, $from, $to);
            $day = $day->addDay();
        }

        return $total;
    }

    private function openMinutesOnDay(CarbonImmutable $day, $hours, $holidays, CarbonInterface $from, CarbonInterface $to): int
    {
        if ($holidays->has($day->toDateString())) {
            return 0;
        }
        $row = $hours->get($day->dayOfWeek);
        // Tak terkonfigurasi → anggap buka penuh hari itu (selaras OutletCalendar OPS-106).
        if ($row !== null && $row->is_closed) {
            return 0;
        }
        $openStr = $row && $row->open_time ? substr((string) $row->open_time, 0, 5) : '00:00';
        $closeStr = $row && $row->close_time ? substr((string) $row->close_time, 0, 5) : '23:59';

        [$oh, $om] = array_map('intval', explode(':', $openStr));
        [$ch, $cm] = array_map('intval', explode(':', $closeStr));
        $open = $day->setTime($oh, $om);
        $close = $day->setTime($ch, $cm);

        // Iris ke jendela [from,to].
        $s = $open->greaterThan($from) ? $open : CarbonImmutable::instance($from);
        $e = $close->lessThan($to) ? $close : CarbonImmutable::instance($to);

        return $e->greaterThan($s) ? (int) round($s->diffInMinutes($e)) : 0;
    }
}
