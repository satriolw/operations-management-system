<?php

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Modules\Signals\SilentOutletCheck;
use App\Support\Observability\Alerter;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
    'cache.default' => 'array', 'oms.metrics_cache_store' => 'array',
]));

it('raiseOnce: alert sama per key per hari hanya sekali', function () {
    Event::fake([OpsAlertRaised::class]);

    expect(Alerter::raiseOnce('k1', 'outlet.silent', []))->toBeTrue()
        ->and(Alerter::raiseOnce('k1', 'outlet.silent', []))->toBeFalse() // dedup
        ->and(Alerter::raiseOnce('k2', 'outlet.silent', []))->toBeTrue(); // key beda → boleh

    Event::assertDispatchedTimes(OpsAlertRaised::class, 2);
});

it('OPS-503: cek outlet-diam berulang → maksimal 1 alert per outlet/titik-cek/hari', function () {
    Event::fake([OpsAlertRaised::class]);
    Outlet::factory()->create(['id_outlet' => 120, 'silent_threshold_pct' => 40]);
    OutletBaseline::create(['id_outlet' => 120, 'checkpoint_hour' => 11, 'avg_txn' => 100, 'sample_days' => 30]);
    Http::fake(['*reports/dashboard*' => Http::response(['txn_count' => 0])]);

    app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);
    app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);
    app(SilentOutletCheck::class)->check(120, '2026-06-12', 11);

    Event::assertDispatchedTimes(OpsAlertRaised::class, 1); // 1 alert, bukan 3
});
