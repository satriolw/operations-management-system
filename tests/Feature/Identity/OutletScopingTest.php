<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\RevenueAdjustment;
use App\Models\SignalEvent;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    foreach ([120, 121, 122] as $id) {
        Outlet::factory()->create(['id_outlet' => $id]);
        ReportRun::create(['id_outlet' => $id, 'report_date' => '2026-06-12', 'status' => 'generated']);
        SignalEvent::create(['id_outlet' => $id, 'type' => 'SILENT_OUTLET', 'severity' => 'high', 'status' => 'OPEN', 'detected_at' => now()]);
        RevenueAdjustment::create([
            'id_outlet' => $id, 'transaction_number' => "INV/{$id}/1", 'type' => 'VOID', 'amount' => 1000,
            'nota_date' => '2026-06-11', 'approved_at' => '2026-06-12', 'restated_for_date' => '2026-06-11',
        ]);
    }
});

function areaManager(array $outletIds): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER);
    $u->outlets()->sync($outletIds);

    return $u;
}

it('area manager hanya melihat report_runs outlet binaannya', function () {
    $am = areaManager([120, 121]);

    $ids = ReportRun::visibleTo($am)->pluck('id_outlet')->sort()->values()->all();
    expect($ids)->toBe([120, 121])
        ->and($ids)->not->toContain(122);
});

it('scoping berlaku utk signal_events & revenue_adjustments juga', function () {
    $am = areaManager([122]);

    expect(SignalEvent::visibleTo($am)->pluck('id_outlet')->all())->toBe([122])
        ->and(RevenueAdjustment::visibleTo($am)->pluck('id_outlet')->all())->toBe([122]);
});

it('admin melihat semua outlet (tanpa filter)', function () {
    $admin = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);

    expect(ReportRun::visibleTo($admin)->count())->toBe(3)
        ->and($admin->canAccessAllOutlets())->toBeTrue();
});

it('user tanpa assignment → tidak melihat apa pun (fail-closed)', function () {
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS); // tanpa outlets()->sync

    expect(ReportRun::visibleTo($u)->count())->toBe(0);
});

it('tamu (null) → tidak melihat apa pun', function () {
    expect(ReportRun::visibleTo(null)->count())->toBe(0);
});

it('TIDAK ada kebocoran lintas Area Manager', function () {
    $am1 = areaManager([120]);
    $am2 = areaManager([121]);

    expect(ReportRun::visibleTo($am1)->pluck('id_outlet')->all())->toBe([120])
        ->and(ReportRun::visibleTo($am2)->pluck('id_outlet')->all())->toBe([121]);
    // am1 tak bisa lihat outlet am2
    expect(ReportRun::visibleTo($am1)->where('id_outlet', 121)->exists())->toBeFalse();
});

it('canAccessOutlet & gate access-outlet menghormati assignment', function () {
    $am = areaManager([120]);
    $admin = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);

    expect($am->canAccessOutlet(120))->toBeTrue()
        ->and($am->canAccessOutlet(121))->toBeFalse()
        ->and($admin->canAccessOutlet(999))->toBeTrue();

    expect(Gate::forUser($am)->allows('access-outlet', 120))->toBeTrue()
        ->and(Gate::forUser($am)->allows('access-outlet', 121))->toBeFalse();
});
