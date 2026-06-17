<?php

use App\Models\NeviraBalanceSnapshot;
use App\Modules\Signals\BurnRateCalculator;
use App\Support\Time\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ASOF = '2026-06-17 12:00:00';

function snap(string $date, int $saldo): void
{
    NeviraBalanceSnapshot::create([
        'captured_at' => Wib::parse($date),
        'saldo_total' => $saldo,
        'breakdown_json' => [],
    ]);
}

it('kurang dari 2 snapshot → null (belum cukup data)', function () {
    snap('2026-06-17 06:00:00', 1000000);
    expect(app(BurnRateCalculator::class)->compute(ASOF))->toBeNull();
});

it('burn harian & runway dari penurunan saldo', function () {
    // turun 100k/hari, 8 snapshot harian
    $saldo = 900000;
    foreach (range(10, 17) as $d) {
        snap("2026-06-{$d} 06:00:00", $saldo);
        $saldo -= 100000;
    }

    $r = app(BurnRateCalculator::class)->compute(ASOF);
    // burn konservatif = maks(7d,3d); runway = saldo terakhir ÷ burn
    expect($r->burnPerDay)->toEqual(max($r->burn7d, $r->burn3d))
        ->and($r->burnPerDay)->toBeGreaterThan(0)
        ->and($r->runwayDays)->toEqual(round($r->saldoTotal / $r->burnPerDay, 2));
});

it('konservatif: lonjakan burn 3-hari terakhir → burn = burn3d (runway lebih pendek)', function () {
    // 7 hari awal nyaris flat (turun 10k/hari), 3 hari terakhir terjun (turun 300k/hari)
    snap('2026-06-10 06:00:00', 2000000);
    snap('2026-06-11 06:00:00', 1990000);
    snap('2026-06-12 06:00:00', 1980000);
    snap('2026-06-13 06:00:00', 1970000);
    snap('2026-06-14 06:00:00', 1960000); // sampai sini ~10k/hari
    snap('2026-06-15 06:00:00', 1660000);
    snap('2026-06-16 06:00:00', 1360000);
    snap('2026-06-17 06:00:00', 1060000); // 3 hari terakhir 300k/hari

    $r = app(BurnRateCalculator::class)->compute(ASOF);
    expect($r->burn3d)->toBeGreaterThan($r->burn7d)
        ->and($r->burnPerDay)->toEqual($r->burn3d); // ambil yang lebih besar
});

it('top-up (saldo naik) dikecualikan dari burn (bukan burn negatif)', function () {
    snap('2026-06-15 06:00:00', 500000);
    snap('2026-06-16 06:00:00', 300000);   // turun 200k (burn)
    snap('2026-06-16 12:00:00', 2000000);  // top-up +1.7jt → DIKECUALIKAN
    snap('2026-06-17 06:00:00', 1850000);  // turun 150k (burn)

    $r = app(BurnRateCalculator::class)->compute(ASOF);
    expect($r->burnPerDay)->toBeGreaterThan(0); // top-up tak membuat burn negatif
});

it('tanpa konsumsi (saldo flat) → burn 0, runway null (aman)', function () {
    snap('2026-06-15 06:00:00', 1000000);
    snap('2026-06-16 06:00:00', 1000000);
    snap('2026-06-17 06:00:00', 1000000);

    $r = app(BurnRateCalculator::class)->compute(ASOF);
    expect($r->burnPerDay)->toEqual(0.0)->and($r->runwayDays)->toBeNull();
});
