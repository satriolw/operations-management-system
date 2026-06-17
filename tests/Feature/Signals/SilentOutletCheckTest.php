<?php

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Models\OutletHoliday;
use App\Models\SignalEvent;
use App\Modules\Signals\SilentOutletCheck;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
        'cache.default' => 'array', 'oms.metrics_cache_store' => 'array',
    ]);
    Outlet::factory()->create(['id_outlet' => 120, 'silent_threshold_pct' => 40]);
});

function fakeTxnCount(int $count): void
{
    Http::fake(['*reports/dashboard*' => Http::response(['txn_count' => $count])]);
}

function baseline(int $avg, int $sample): void
{
    OutletBaseline::create(['id_outlet' => 120, 'checkpoint_hour' => 11, 'avg_txn' => $avg, 'sample_days' => $sample]);
}

it('outlet diam (realisasi < ambang × baseline) → signal high + alert', function () {
    Event::fake([OpsAlertRaised::class]);
    baseline(100, 30);          // avg 100, ambang 40% → expected 40
    fakeTxnCount(5);            // 5 < 40 → diam

    $sig = app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);

    expect($sig)->not->toBeNull()
        ->and($sig->type)->toBe('SILENT_OUTLET')
        ->and($sig->severity)->toBe('high')
        ->and($sig->payload_json['realized'])->toBe(5);
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'outlet.silent');
});

it('transaksi sehat (≥ ambang) → tidak ada signal', function () {
    baseline(100, 30);
    fakeTxnCount(60); // 60 ≥ 40

    expect(app(SilentOutletCheck::class)->check(120, '2026-06-12', 11))->toBeNull()
        ->and(SignalEvent::count())->toBe(0);
});

it('OPS-106: hari libur/tutup → tidak ada alarm meski nol transaksi', function () {
    baseline(100, 30);
    fakeTxnCount(0);
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-06-12']);

    expect(app(SilentOutletCheck::class)->check(120, '2026-06-12', 11))->toBeNull();
});

it('outlet baru (sample < 14): ada transaksi → TIDAK dialarm (baseline belum dipercaya)', function () {
    baseline(100, 5);
    fakeTxnCount(3); // 3 > 0 → konservatif: tak dialarm
    expect(app(SilentOutletCheck::class)->check(120, '2026-06-12', 11))->toBeNull();
});

it('outlet baru (sample < 14): nol transaksi → dialarm', function () {
    baseline(100, 5);
    fakeTxnCount(0);
    expect(app(SilentOutletCheck::class)->check(120, '2026-06-12', 11))->not->toBeNull();
});

it('idempoten: cek dua kali titik cek sama → satu signal', function () {
    Event::fake([OpsAlertRaised::class]);
    baseline(100, 30);
    fakeTxnCount(0);

    app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);
    app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);

    expect(SignalEvent::where('id_outlet', 120)->where('type', 'SILENT_OUTLET')->count())->toBe(1);
});
