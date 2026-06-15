<?php

namespace App\Modules\Ingestion\DTO;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rentang tanggal inklusif untuk query NEVIRA. Dinormalkan ke Asia/Jakarta (WIB).
 * Filter start_date/end_date NEVIRA bekerja pada created_at (PRD §6.2).
 */
final class DateRange
{
    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    public function __construct(CarbonInterface|string $start, CarbonInterface|string $end)
    {
        $this->start = CarbonImmutable::parse($start, 'Asia/Jakarta')->startOfDay();
        $this->end = CarbonImmutable::parse($end, 'Asia/Jakarta')->endOfDay();
    }

    /** Jendela lookback ke belakang dari satu tanggal acuan (mis. 7 hari untuk Penyesuaian Revenue). */
    public static function lookback(CarbonInterface|string $reference, int $days): self
    {
        $ref = CarbonImmutable::parse($reference, 'Asia/Jakarta');

        return new self($ref->subDays($days), $ref);
    }

    public function startDate(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function endDate(): string
    {
        return $this->end->format('Y-m-d');
    }
}
