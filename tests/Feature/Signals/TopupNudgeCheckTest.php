<?php

use App\Models\NeviraBalanceSnapshot;
use App\Models\NeviraTopupConfig;
use App\Models\SignalEvent;
use App\Modules\Signals\TopupNudgeCheck;
use App\Support\Time\Wib;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Kalender 2026-06: 15 Sen, 16 Sel, 17 Rab, 18 Kam, 19 Jum, 22 Sen, 25 Kam.
// Config Senin(1)/Kamis(4), lead 24 jam, buffer 3.

function nsnap(string $date, int $saldo): void
{
    NeviraBalanceSnapshot::create(['captured_at' => Wib::parse($date), 'saldo_total' => $saldo, 'breakdown_json' => []]);
}

/** 2 snapshot 1 hari → burn = drop/3; runway = saldo ÷ burn. */
function runwayTen(string $d1, string $d2): void
{
    nsnap($d1, 1300000);
    nsnap($d2, 1000000); // drop 300k → burn 100k → runway 10
}

beforeEach(fn () => NeviraTopupConfig::factory()->create([
    'disbursement_weekdays' => [1, 4], 'submission_cutoff_lead_hours' => 24, 'buffer_days' => 3,
    'warning_runway_days' => 8, 'critical_runway_days' => 5,
]));

it('proyeksi aman → tak ada nudge', function () {
    nsnap('2026-06-15 06:00:00', 3100000);
    nsnap('2026-06-16 06:00:00', 3000000); // drop 100k → burn 33.3k → runway ~90
    expect(app(TopupNudgeCheck::class)->check('2026-06-16 09:00:00'))->toBeNull();
});

it('window Kamis: runway 10 < horizon (buffer ekstra akhir pekan) → nudge', function () {
    // now Selasa 06-16 → target window Kamis 06-18, after Senin 06-22.
    // horizon = ceil(06-16 09:00 → 06-22) + buffer(3+2 Kamis) ≈ 6 + 5 = 11. runway 10 < 11 → nudge.
    runwayTen('2026-06-15 06:00:00', '2026-06-16 06:00:00');

    $s = app(TopupNudgeCheck::class)->check('2026-06-16 09:00:00');
    expect($s)->not->toBeNull()
        ->and($s->type)->toBe('TOPUP_NUDGE')
        ->and($s->severity)->toBe('high')
        ->and($s->id_outlet)->toBeNull()
        ->and($s->payload_json['window_weekday'])->toBe(4)        // Kamis
        ->and($s->payload_json['next_window_date'])->toBe('2026-06-22')
        ->and($s->payload_json['runway_days'])->toEqual(10.0);
});

it('window Senin: runway 10 ≥ horizon (tanpa buffer ekstra) → TIDAK nudge', function () {
    // now Jumat 06-19 → target Senin 06-22, after Kamis 06-25.
    // horizon = ceil(06-19 09:00 → 06-25) + buffer 3 ≈ 6 + 3 = 9. runway 10 ≥ 9 → aman.
    runwayTen('2026-06-18 06:00:00', '2026-06-19 06:00:00');

    expect(app(TopupNudgeCheck::class)->check('2026-06-19 09:00:00'))->toBeNull();
});

it('idempoten per window (tidak spam, satu nudge)', function () {
    runwayTen('2026-06-15 06:00:00', '2026-06-16 06:00:00');

    app(TopupNudgeCheck::class)->check('2026-06-16 09:00:00');
    app(TopupNudgeCheck::class)->check('2026-06-16 15:00:00'); // hari sama, window sama

    expect(SignalEvent::where('type', 'TOPUP_NUDGE')->count())->toBe(1);
});

it('data burn tak cukup (<2 snapshot) → tak ada nudge', function () {
    nsnap('2026-06-16 06:00:00', 1000000);
    expect(app(TopupNudgeCheck::class)->check('2026-06-16 09:00:00'))->toBeNull();
});

it('command oms:check-balance-signals jalan tanpa error', function () {
    runwayTen('2026-06-15 06:00:00', '2026-06-16 06:00:00');
    $this->artisan('oms:check-balance-signals', ['--date' => '2026-06-16 09:00:00'])->assertSuccessful();
});
