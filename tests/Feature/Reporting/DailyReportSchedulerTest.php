<?php

use App\Models\Outlet;
use App\Modules\Reporting\Scheduling\DailyReportScheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scheduledEvents(): \Illuminate\Support\Collection
{
    // Schedule terisolasi (bukan singleton app) agar entri boot-time lain (mis. retensi OPS-705)
    // tidak mengotori asersi jumlah event.
    $schedule = new Schedule();
    DailyReportScheduler::apply($schedule);

    return collect($schedule->events());
}

it('menjadwalkan satu job per outlet aktif pada jamnya, timezone Asia/Jakarta', function () {
    Outlet::factory()->create(['id_outlet' => 120, 'report_time' => '09:00', 'active' => true]);
    Outlet::factory()->create(['id_outlet' => 117, 'report_time' => '17:30', 'active' => true]);

    $events = scheduledEvents();

    expect($events)->toHaveCount(2);
    $events->each(fn ($e) => expect((string) $e->timezone)->toBe('Asia/Jakarta'));

    // ekspresi cron per jam outlet
    expect($events->map(fn ($e) => $e->expression)->all())
        ->toContain('0 9 * * *')   // 09:00
        ->toContain('30 17 * * *'); // 17:30
});

it('outlet non-aktif tidak dijadwalkan', function () {
    Outlet::factory()->create(['id_outlet' => 120, 'report_time' => '09:00', 'active' => true]);
    Outlet::factory()->inactive()->create(['id_outlet' => 200, 'report_time' => '10:00']);

    expect(scheduledEvents())->toHaveCount(1);
});

it('outlet tanpa report_time dilewati', function () {
    Outlet::factory()->create(['id_outlet' => 120, 'report_time' => '09:00', 'active' => true]);
    Outlet::factory()->create(['id_outlet' => 121, 'report_time' => null, 'active' => true]);

    expect(scheduledEvents())->toHaveCount(1);
});

it('report_time HH:MM:SS dinormalkan ke HH:MM', function () {
    Outlet::factory()->create(['id_outlet' => 120, 'report_time' => '21:30:00', 'active' => true]);

    expect(scheduledEvents()->first()->expression)->toBe('30 21 * * *');
});
