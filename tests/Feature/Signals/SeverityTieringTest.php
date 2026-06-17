<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Signals\SignalDigest;
use App\Modules\Signals\SignalRouter;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function sig(string $type, string $severity, string $status = 'OPEN'): SignalEvent
{
    return SignalEvent::create([
        'id_outlet' => 120, 'type' => $type, 'severity' => $severity, 'status' => $status,
        'detected_at' => now(), 'ref_transaction_number' => 'INV/'.$type,
    ]);
}

it('HIGH → alert real-time', function () {
    Event::fake([OpsAlertRaised::class]);
    app(SignalRouter::class)->notify(sig('SELF_APPROVAL', 'high'));
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'signal.self_approval');
});

it('LOW → TIDAK real-time (masuk digest)', function () {
    Event::fake([OpsAlertRaised::class]);
    app(SignalRouter::class)->notify(sig('AGING_PIUTANG', 'low'));
    Event::assertNotDispatched(OpsAlertRaised::class);
});

it('digest mengumpulkan sinyal low OPEN (high & dismissed dikecualikan)', function () {
    Event::fake([OpsAlertRaised::class]);
    sig('AGING_PIUTANG', 'low');
    sig('INPUT_ERROR_KPI', 'low');
    sig('AGING_PIUTANG', 'low', 'DISMISSED'); // bukan OPEN → tak dihitung
    sig('SELF_APPROVAL', 'high');             // high → tak masuk digest

    $r = app(SignalDigest::class)->build();

    expect($r['total'])->toBe(2)
        ->and($r['by_type'])->toMatchArray(['AGING_PIUTANG' => 1, 'INPUT_ERROR_KPI' => 1]);
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'signals.digest');
});

it('digest kosong → tidak ada alert', function () {
    Event::fake([OpsAlertRaised::class]);
    app(SignalDigest::class)->build();
    Event::assertNotDispatched(OpsAlertRaised::class);
});

it('command oms:signal-digest jalan', function () {
    sig('AGING_PIUTANG', 'low');
    $this->artisan('oms:signal-digest')->assertSuccessful();
});
