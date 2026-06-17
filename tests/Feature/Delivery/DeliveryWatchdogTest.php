<?php

use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Delivery\DeliveryWatchdog;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']));

function runWith(int $outlet, string $status): ReportRun
{
    Outlet::factory()->create(['id_outlet' => $outlet]);
    $run = ReportRun::create(['id_outlet' => $outlet, 'report_date' => '2026-06-12', 'status' => 'generated']);
    $run->deliveries()->create([
        'id_outlet' => $outlet, 'channel' => 'hybrid', 'status' => $status, 'idempotency_key' => "k{$outlet}",
    ]);

    return $run;
}

it('outlet dgn pengiriman TERKONFIRMASI → tidak alert', function () {
    Event::fake([OpsAlertRaised::class]);
    runWith(120, ReportDelivery::CONFIRMED_SENT);

    expect(app(DeliveryWatchdog::class)->check('2026-06-12'))->toBe([]);
    Event::assertNotDispatched(OpsAlertRaised::class);
});

it('GOTCHA: draft awaiting_confirmation BUKAN terkirim → alert (watchdog pakai status konfirmasi)', function () {
    Event::fake([OpsAlertRaised::class]);
    runWith(120, ReportDelivery::AWAITING_CONFIRMATION); // hanya draft ke Head Store

    expect(app(DeliveryWatchdog::class)->check('2026-06-12'))->toBe([120]);
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'report.not_delivered' && $e->context['id_outlet'] === 120);
});

it('outlet tanpa report_run → alert', function () {
    Event::fake([OpsAlertRaised::class]);
    Outlet::factory()->create(['id_outlet' => 121]);

    expect(app(DeliveryWatchdog::class)->check('2026-06-12'))->toBe([121]);
});

it('outlet non-aktif diabaikan watchdog', function () {
    Event::fake([OpsAlertRaised::class]);
    Outlet::factory()->inactive()->create(['id_outlet' => 200]);

    expect(app(DeliveryWatchdog::class)->check('2026-06-12'))->toBe([]);
});

it('command oms:watchdog-deliveries jalan & lapor', function () {
    runWith(120, ReportDelivery::CONFIRMED_SENT);
    $this->artisan('oms:watchdog-deliveries', ['--date' => '2026-06-12'])->assertSuccessful();
});
