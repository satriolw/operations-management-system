<?php

namespace App\Modules\Signals;

use App\Models\NeviraBalanceSnapshot;
use App\Modules\Signals\DTO\BurnRunway;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Burn rate + runway saldo merchant (OPS-1202, System Design §3.15). Dari deret snapshot
 * (OPS-1201): konsumsi = penurunan saldo antar-snapshot (top-up = delta positif, DIKECUALIKAN).
 *
 *   burn_harian = maks( rata2 7-hari , rata2 3-hari )   # konservatif → tahan lonjakan
 *   runway_hari = saldo_total / burn_harian
 *
 * Konservatif (ambil yang lebih besar) agar lonjakan burn terbaru tidak tersamarkan rata-rata
 * panjang → runway lebih pendek (aman). Dipakai alert bertingkat (OPS-1204) & nudge (OPS-1205).
 */
final class BurnRateCalculator
{
    public function compute(CarbonInterface|string|null $asOf = null): ?BurnRunway
    {
        $asOf = $asOf === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $asOf));

        $snaps = NeviraBalanceSnapshot::query()
            ->where('captured_at', '<=', $asOf)
            ->orderBy('captured_at')
            ->get();

        if ($snaps->count() < 2) {
            return null; // belum cukup data untuk burn
        }

        $burn7 = $this->avgDailyBurn($snaps, $asOf, 7);
        $burn3 = $this->avgDailyBurn($snaps, $asOf, 3);
        $burn = max($burn7, $burn3); // konservatif

        $saldo = (int) $snaps->last()->saldo_total;
        $runway = $burn > 0 ? round($saldo / $burn, 2) : null; // burn 0 → tak ada konsumsi (aman)

        return new BurnRunway($saldo, round($burn, 2), $runway, round($burn7, 2), round($burn3, 2));
    }

    /** Rata-rata konsumsi harian pada jendela $days terakhir (hanya penurunan saldo). */
    private function avgDailyBurn(Collection $snaps, CarbonInterface $asOf, int $days): float
    {
        $from = $asOf->copy()->subDays($days);
        $window = $snaps->filter(fn (NeviraBalanceSnapshot $s) => $s->captured_at->gte($from))->values();

        $consumption = 0.0;
        $prev = null;
        foreach ($window as $s) {
            if ($prev !== null) {
                $drop = (int) $prev->saldo_total - (int) $s->saldo_total;
                if ($drop > 0) {
                    $consumption += $drop; // top-up (drop < 0) dikecualikan
                }
            }
            $prev = $s;
        }

        return $consumption / $days;
    }
}
