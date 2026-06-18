<?php

use App\Models\Outlet;
use App\Models\OutletSlaConfig;
use App\Models\SignalEvent;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses SLA config tanpa master_data.edit (403)', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);

    $this->actingAs($ops)->get(route('admin.sla-config.index'))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.sla-config.update', $o), [])->assertForbidden();
});

it('index render 200 + default aman utk outlet belum dikonfigurasi', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->actingAs(admin())->get(route('admin.sla-config.index'))->assertOk();

    $cfg = OutletSlaConfig::forOutlet(120); // belum tersimpan → default
    expect($cfg->sla_clock_mode)->toBe('business_hours')->and($cfg->grace_minutes)->toBe(30);
});

it('update SLA per outlet (updateOrCreate)', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);

    $this->actingAs(admin())->put(route('admin.sla-config.update', $o), [
        'sla_clock_mode' => 'wallclock', 'grace_minutes' => 45, 'approaching_lead_minutes' => 90,
        'stuck_minutes_threshold' => 300, 'minor_overdue_minutes' => 150,
    ])->assertRedirect();

    $cfg = OutletSlaConfig::forOutlet(120);
    expect($cfg->sla_clock_mode)->toBe('wallclock')->and($cfg->grace_minutes)->toBe(45);
    expect(OutletSlaConfig::where('id_outlet', 120)->count())->toBe(1);
});

it('tolak mode jam tak valid', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    $this->actingAs(admin())->put(route('admin.sla-config.update', $o), [
        'sla_clock_mode' => 'lunar', 'grace_minutes' => 30, 'approaching_lead_minutes' => 120,
        'stuck_minutes_threshold' => 240, 'minor_overdue_minutes' => 120,
    ])->assertSessionHasErrors('sla_clock_mode');
});

it('dashboard menampilkan jumlah nota terlambat (LATE_ORDER terbuka)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    SignalEvent::create(['id_outlet' => 120, 'type' => 'LATE_ORDER', 'severity' => 'high', 'status' => 'OPEN', 'detected_at' => now()]);

    $admin = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertViewHas('lateOrders', 1);
});
