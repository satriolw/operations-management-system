<?php

use App\Models\NeviraRoleLevel;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('layar HTML menampilkan peta role (bukan JSON)', function () {
    NeviraRoleLevel::create(['id_role' => 3, 'label' => 'Kepala Toko', 'level' => 50, 'dual_authority_allowed' => true]);

    $this->actingAs(admin())->get(route('admin.role-levels.index'))
        ->assertOk()->assertSee('Kepala Toko')->assertSee('Peta Role');
});

it('tambah via form HTML → redirect back + tersimpan', function () {
    $this->actingAs(admin())->from(route('admin.role-levels.index'))
        ->post(route('admin.role-levels.store'), ['id_role' => 7, 'label' => 'Supervisor', 'level' => 40, 'dual_authority_allowed' => '1'])
        ->assertRedirect(route('admin.role-levels.index'));

    expect(NeviraRoleLevel::where('id_role', 7)->first()->level)->toBe(40);
});

it('hapus via form HTML → redirect back', function () {
    $rl = NeviraRoleLevel::create(['id_role' => 9, 'label' => 'X', 'level' => 10, 'dual_authority_allowed' => false]);

    $this->actingAs(admin())->from(route('admin.role-levels.index'))
        ->delete(route('admin.role-levels.destroy', $rl))->assertRedirect();
    expect(NeviraRoleLevel::count())->toBe(0);
});

it('non-admin ditolak (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.role-levels.index'))->assertForbidden();
});
