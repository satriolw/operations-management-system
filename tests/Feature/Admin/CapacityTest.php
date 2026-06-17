<?php

use App\Models\Outlet;
use App\Models\OutletCapacity;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses kapasitas tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $outlet = Outlet::factory()->create();

    $this->actingAs($ops)->get(route('admin.capacity.index'))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.capacity.update', $outlet), [])->assertForbidden();
});

it('index render 200 utk admin', function () {
    Outlet::factory()->create();
    $this->actingAs(admin())->get(route('admin.capacity.index'))
        ->assertOk()->assertViewIs('admin.capacity.index');
});

it('CRUD kapasitas per outlet (updateOrCreate, ambang per outlet)', function () {
    $outlet = Outlet::factory()->create();

    $this->actingAs(admin())->put(route('admin.capacity.update', $outlet), [
        'machines' => 4, 'throughput_kg_per_machine_hour' => 10,
        'shift_hours' => 10, 'overload_threshold_pct' => 85,
    ])->assertRedirect(route('admin.capacity.index'));

    $c = $outlet->capacity()->first();
    expect($c)->not->toBeNull()
        ->and($c->machines)->toBe(4)
        ->and($c->overload_threshold_pct)->toBe(85);

    // update lagi tak menggandakan baris
    $this->actingAs(admin())->put(route('admin.capacity.update', $outlet), [
        'machines' => 6, 'throughput_kg_per_machine_hour' => 10, 'shift_hours' => 10, 'overload_threshold_pct' => 90,
    ])->assertRedirect();
    expect(OutletCapacity::where('id_outlet', $outlet->id_outlet)->count())->toBe(1)
        ->and($outlet->capacity()->first()->machines)->toBe(6);
});

it('effective capacity: mesin × throughput', function () {
    $c = OutletCapacity::factory()->make([
        'machines' => 4, 'throughput_kg_per_machine_hour' => 10,
        'kg_per_day' => null, 'capacity_kg_per_hour' => null,
    ]);
    expect($c->effectiveKgPerHour())->toBe(40.0)->and($c->isConfigured())->toBeTrue();
});

it('effective capacity: kg/hari ÷ jam shift bila mesin tak ada', function () {
    $c = OutletCapacity::factory()->make([
        'machines' => null, 'throughput_kg_per_machine_hour' => null,
        'kg_per_day' => 400, 'shift_hours' => 10, 'capacity_kg_per_hour' => null,
    ]);
    expect($c->effectiveKgPerHour())->toBe(40.0);
});

it('override kg/jam mengalahkan turunan', function () {
    $c = OutletCapacity::factory()->make([
        'machines' => 4, 'throughput_kg_per_machine_hour' => 10, // turunan = 40
        'capacity_kg_per_hour' => 55, // override menang
    ]);
    expect($c->effectiveKgPerHour())->toBe(55.0);
});

it('input tak cukup → effective null & isConfigured false', function () {
    $c = OutletCapacity::factory()->make([
        'machines' => null, 'throughput_kg_per_machine_hour' => null,
        'kg_per_day' => null, 'shift_hours' => null, 'capacity_kg_per_hour' => null,
    ]);
    expect($c->effectiveKgPerHour())->toBeNull()->and($c->isConfigured())->toBeFalse();
});

it('tolak simpan bila kapasitas tak dapat diturunkan (validasi)', function () {
    $outlet = Outlet::factory()->create();

    $this->actingAs(admin())->put(route('admin.capacity.update', $outlet), [
        'overload_threshold_pct' => 80, // tak ada jalur kapasitas
    ])->assertSessionHasErrors('capacity_kg_per_hour');

    expect(OutletCapacity::count())->toBe(0);
});

it('tolak ambang overload di luar 1..100', function () {
    $outlet = Outlet::factory()->create();
    $this->actingAs(admin())->put(route('admin.capacity.update', $outlet), [
        'machines' => 4, 'throughput_kg_per_machine_hour' => 10, 'overload_threshold_pct' => 0,
    ])->assertSessionHasErrors('overload_threshold_pct');
});
