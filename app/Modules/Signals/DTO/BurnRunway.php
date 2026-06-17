<?php

namespace App\Modules\Signals\DTO;

/**
 * Hasil burn rate + runway saldo merchant (OPS-1202, Epic L). burnPerDay konservatif =
 * maks(rata2 7-hari, rata2 3-hari). runwayDays null = burn 0 (tak ada konsumsi → aman).
 */
final class BurnRunway
{
    public function __construct(
        public readonly int $saldoTotal,
        public readonly float $burnPerDay,   // konservatif = maks(7d, 3d)
        public readonly ?float $runwayDays,  // saldo ÷ burn; null bila burn 0
        public readonly float $burn7d,
        public readonly float $burn3d,
    ) {}
}
