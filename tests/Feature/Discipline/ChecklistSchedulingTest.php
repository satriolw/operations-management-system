<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use App\Models\Outlet;
use App\Models\OutletHoliday;
use App\Models\User;
use App\Modules\Discipline\ChecklistDeadlineCheck;
use App\Modules\Discipline\ChecklistScheduler;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

const DAY = '2026-06-18'; // Kamis

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
});

it('createDailyRuns: run per outlet aktif dari template grup, idempoten', function () {
    ChecklistTemplate::factory()->create(['id_outlet' => null, 'schedule' => 'daily', 'active' => true]); // grup

    $first = app(ChecklistScheduler::class)->createDailyRuns(DAY);
    $second = app(ChecklistScheduler::class)->createDailyRuns(DAY); // rerun

    expect($first)->toHaveCount(2)         // 2 outlet × 1 template grup
        ->and(ChecklistRun::count())->toBe(2); // idempoten, tak menggandakan
});

it('createDailyRuns: outlet tutup/libur di-skip (OPS-106)', function () {
    ChecklistTemplate::factory()->create(['id_outlet' => null, 'schedule' => 'daily', 'active' => true]);
    OutletHoliday::create(['id_outlet' => 121, 'holiday_date' => DAY, 'note' => 'Libur']);

    app(ChecklistScheduler::class)->createDailyRuns(DAY);

    expect(ChecklistRun::where('id_outlet', 121)->count())->toBe(0)
        ->and(ChecklistRun::where('id_outlet', 120)->count())->toBe(1);
});

/** Run + 2 item (1 wajib foto, 1 catatan). */
function runWithItems(): ChecklistRun
{
    $t = ChecklistTemplate::factory()->create(['id_outlet' => 120]);
    ChecklistItem::factory()->create(['template_id' => $t->id, 'requires_photo' => true, 'label' => 'Foto']);
    ChecklistItem::factory()->create(['template_id' => $t->id, 'requires_photo' => false, 'label' => 'Catatan']);

    return ChecklistRun::create(['id_outlet' => 120, 'template_id' => $t->id, 'run_date' => DAY, 'status' => 'open']);
}

it('deadline: run lengkap → status complete, tanpa alert', function () {
    Event::fake([OpsAlertRaised::class]);
    $run = runWithItems();
    $u = User::factory()->create();
    foreach ($run->template->items as $item) {
        ChecklistSubmission::create([
            'run_id' => $run->id, 'item_id' => $item->id, 'crew_user_id' => $u->id,
            'photo_ref' => $item->requires_photo ? 'p.jpg' : null, 'captured_at_server' => now(),
        ]);
    }

    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 21:00:00');

    expect($run->fresh()->status)->toBe('complete');
    Event::assertNotDispatched(OpsAlertRaised::class);
});

it('deadline: belum lengkap lewat jam reminder → reminder (status tetap open)', function () {
    Event::fake([OpsAlertRaised::class]);
    $run = runWithItems(); // tanpa submission

    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 13:00:00'); // > reminder(12), < eskalasi(20)

    expect($run->fresh()->status)->toBe('open');
    Event::assertDispatched(OpsAlertRaised::class, fn (OpsAlertRaised $e) => $e->code === 'checklist.reminder');
});

it('deadline: belum lengkap lewat jam eskalasi → MISSED + eskalasi', function () {
    Event::fake([OpsAlertRaised::class]);
    $run = runWithItems();

    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 21:00:00');

    expect($run->fresh()->status)->toBe('missed');
    Event::assertDispatched(OpsAlertRaised::class, fn (OpsAlertRaised $e) => $e->code === 'checklist.missed');
});

it('no-spam: evaluasi ulang jam reminder → satu reminder', function () {
    Event::fake([OpsAlertRaised::class]);
    $run = runWithItems();

    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 13:00:00');
    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 14:00:00');

    Event::assertDispatchedTimes(OpsAlertRaised::class, 1);
});

it('sebelum jam reminder → tak ada alert', function () {
    Event::fake([OpsAlertRaised::class]);
    runWithItems();

    app(ChecklistDeadlineCheck::class)->evaluate(DAY, DAY.' 09:00:00');
    Event::assertNotDispatched(OpsAlertRaised::class);
});
