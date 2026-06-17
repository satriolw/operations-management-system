<?php

use App\Models\NeviraBalanceSnapshot;
use App\Models\NeviraTopupConfig;
use App\Models\SignalEvent;
use App\Modules\Signals\RunwayAlertCheck;
use App\Support\Time\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const AT = '2026-06-17 09:00:00';

/**
 * Snapshot helper. Dengan 2 snapshot berjarak 1 hari, burn konservatif = drop/3 (window 3-hari),
 * sehingga runway = saldo_terakhir ÷ (drop/3). Lihat BurnRateCalculator (OPS-1202).
 */
function bsnap(string $date, int $saldo): void
{
    NeviraBalanceSnapshot::create(['captured_at' => Wib::parse($date), 'saldo_total' => $saldo, 'breakdown_json' => []]);
}

beforeEach(function () {
    NeviraTopupConfig::factory()->create(['warning_runway_days' => 8, 'critical_runway_days' => 5]);
});

it('data burn tak tersedia (<2 snapshot) → tak ada alert', function () {
    bsnap('2026-06-17 06:00:00', 1000000);
    expect(app(RunwayAlertCheck::class)->check(AT))->toBeNull();
});

it('runway di atas ambang warning → tak ada alert', function () {
    bsnap('2026-06-16 06:00:00', 1500000);
    bsnap('2026-06-17 06:00:00', 1200000); // drop 300k → burn 100k → runway 12
    expect(app(RunwayAlertCheck::class)->check(AT))->toBeNull();
});

it('runway ≤ warning (>kritis) → sinyal SALDO_NEVIRA high, tier warning', function () {
    bsnap('2026-06-16 06:00:00', 1000000);
    bsnap('2026-06-17 06:00:00', 700000); // drop 300k → burn 100k → runway 7
    $s = app(RunwayAlertCheck::class)->check(AT);

    expect($s->type)->toBe('SALDO_NEVIRA')
        ->and($s->severity)->toBe('high')      // real-time, bukan digest
        ->and($s->id_outlet)->toBeNull()       // merchant-level
        ->and($s->payload_json['tier'])->toBe('warning')
        ->and($s->payload_json['runway_days'])->toEqual(7.0)
        ->and($s->payload_json['saldo_total'])->toBe(700000); // rupiah utk intuisi
});

it('runway ≤ kritis → tier critical, high', function () {
    bsnap('2026-06-16 06:00:00', 700000);
    bsnap('2026-06-17 06:00:00', 400000); // drop 300k → burn 100k → runway 4
    $s = app(RunwayAlertCheck::class)->check(AT);
    expect($s->payload_json['tier'])->toBe('critical')->and($s->severity)->toBe('high');
});

it('idempoten per hari (re-run → tak menggandakan)', function () {
    bsnap('2026-06-16 06:00:00', 700000);
    bsnap('2026-06-17 06:00:00', 400000);
    app(RunwayAlertCheck::class)->check(AT);
    app(RunwayAlertCheck::class)->check('2026-06-17 18:00:00'); // hari sama

    expect(SignalEvent::where('type', 'SALDO_NEVIRA')->count())->toBe(1);
});

it('eskalasi warning→kritis hari sama → update ke critical (tetap 1 baris)', function () {
    bsnap('2026-06-16 06:00:00', 1000000);
    bsnap('2026-06-17 06:00:00', 700000); // runway 7 → warning
    app(RunwayAlertCheck::class)->check(AT);

    bsnap('2026-06-17 07:00:00', 400000); // konsumsi memburuk → runway turun → kritis
    $s = app(RunwayAlertCheck::class)->check(AT);

    expect(SignalEvent::where('type', 'SALDO_NEVIRA')->count())->toBe(1)
        ->and($s->fresh()->payload_json['tier'])->toBe('critical');
});

it('ambang configurable (OPS-1203): kritis diperlebar → memicu lebih dini', function () {
    NeviraTopupConfig::query()->delete();
    NeviraTopupConfig::factory()->create(['warning_runway_days' => 20, 'critical_runway_days' => 15]);
    bsnap('2026-06-16 06:00:00', 1500000);
    bsnap('2026-06-17 06:00:00', 1200000); // runway 12 ≤ 15 → kritis

    expect(app(RunwayAlertCheck::class)->check(AT)->payload_json['tier'])->toBe('critical');
});
