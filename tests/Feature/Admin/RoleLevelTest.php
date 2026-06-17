<?php

use App\Models\NeviraRoleLevel;
use App\Models\User;
use App\Modules\Identity\Permissions;
use App\Modules\Identity\RoleLevelMap;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->getJson(route('admin.role-levels.index'))->assertForbidden();
    $this->actingAs($ops)->postJson(route('admin.role-levels.store'), [])->assertForbidden();
});

it('CRUD peta id_role→level', function () {
    $admin = admin();

    $this->actingAs($admin)->postJson(route('admin.role-levels.store'), [
        'id_role' => 3, 'label' => 'Kepala Toko', 'level' => 50, 'dual_authority_allowed' => true,
    ])->assertCreated();

    $rl = NeviraRoleLevel::where('id_role', 3)->first();
    expect($rl->dual_authority_allowed)->toBeTrue();

    $this->actingAs($admin)->putJson(route('admin.role-levels.update', $rl), [
        'id_role' => 3, 'label' => 'Kepala Toko', 'level' => 60, 'dual_authority_allowed' => false,
    ])->assertOk();
    expect($rl->refresh()->level)->toBe(60)->and($rl->dual_authority_allowed)->toBeFalse();

    $this->actingAs($admin)->deleteJson(route('admin.role-levels.destroy', $rl))->assertOk();
    expect(NeviraRoleLevel::count())->toBe(0);
});

it('id_role duplikat ditolak (tidak menambah baris)', function () {
    NeviraRoleLevel::create(['id_role' => 37, 'label' => 'Kasir', 'level' => 10, 'dual_authority_allowed' => false]);

    $this->actingAs(admin())->post(route('admin.role-levels.store'), [
        'id_role' => 37, 'label' => 'X', 'level' => 5, 'dual_authority_allowed' => false,
    ])->assertSessionHasErrors('id_role');

    expect(NeviraRoleLevel::where('id_role', 37)->count())->toBe(1);
});

it('RoleLevelMap: allowsDualAuthority → bool / null bila tak dipetakan', function () {
    NeviraRoleLevel::create(['id_role' => 3, 'label' => 'Kepala Toko', 'level' => 50, 'dual_authority_allowed' => true]);
    NeviraRoleLevel::create(['id_role' => 37, 'label' => 'Kasir', 'level' => 10, 'dual_authority_allowed' => false]);

    $map = app(RoleLevelMap::class);
    expect($map->allowsDualAuthority(3))->toBeTrue()
        ->and($map->allowsDualAuthority(37))->toBeFalse()
        ->and($map->allowsDualAuthority(999))->toBeNull()   // belum dipetakan
        ->and($map->allowsDualAuthority(null))->toBeNull();
});
