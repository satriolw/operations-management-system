<?php

namespace App\Modules\Revenue\DTO;

/**
 * Hasil Penyesuaian Revenue (OPS-402): total koreksi + revenue lama→baru per tanggal nota.
 */
final class RestateSummary
{
    /**
     * @param  array<string,array{old:?int,correction:int,new:?int,count:int}>  $byDate  per tanggal nota (Y-m-d WIB)
     */
    public function __construct(
        public readonly array $byDate,
        public readonly int $totalCorrection,
    ) {}

    public function isEmpty(): bool
    {
        return $this->byDate === [];
    }
}
