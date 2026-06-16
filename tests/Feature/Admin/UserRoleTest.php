<?php

use App\Models\Outlet;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->km = Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Kemang']);
    $this->cp = Outlet::factory()->create(['id_outlet' => 121, 'name' => 'Cipete']);
});

it('menolak akses tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($ops)->post(route('admin.users.store'), [])->assertForbidden();
});

it('menampilkan daftar user + role + status', function () {
    $u = tap(User::factory()->create(['name' => 'Bagus Pratama']))->assignRole(Permissions::ROLE_AREA_MANAGER);
    $u->outlets()->sync([120]);

    $this->actingAs(admin())->get(route('admin.users.index'))
        ->assertOk()->assertSee('User &amp; Role', false)->assertSee('Bagus Pratama')->assertSee('Kemang');
});

it('undang user: head_store dgn 1 outlet → dibuat status pending + role + scope', function () {
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => 'Rizky Hakim', 'email' => 'rizky@apique.id', 'role' => 'head_store', 'outlets' => [120],
    ])->assertRedirect(route('admin.users.index'))->assertSessionHas('status');

    $u = User::where('email', 'rizky@apique.id')->first();
    expect($u)->not->toBeNull()
        ->and($u->status)->toBe('pending')
        ->and($u->hasRole('head_store'))->toBeTrue()
        ->and($u->outlets()->pluck('outlets.id_outlet')->all())->toBe([120]);
});

it('undang admin: outlet diabaikan (akses semua, tanpa assignment)', function () {
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => 'Aulia', 'email' => 'aulia@apique.id', 'role' => 'admin', 'outlets' => [120, 121],
    ])->assertSessionHasNoErrors();

    $u = User::where('email', 'aulia@apique.id')->first();
    expect($u->hasRole('admin'))->toBeTrue()
        ->and($u->outlets()->count())->toBe(0); // admin = semua, tanpa baris
});

it('undang area_manager: multi outlet ok', function () {
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => 'AM', 'email' => 'am@apique.id', 'role' => 'area_manager', 'outlets' => [120, 121],
    ])->assertSessionHasNoErrors();
    expect(User::where('email', 'am@apique.id')->first()->outlets()->count())->toBe(2);
});

it('VALIDASI: head_store wajib tepat 1 outlet (2 ditolak)', function () {
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => 'X', 'email' => 'x@apique.id', 'role' => 'head_store', 'outlets' => [120, 121],
    ])->assertSessionHasErrors('outlets');
    expect(User::where('email', 'x@apique.id')->exists())->toBeFalse();
});

it('VALIDASI: area/ops wajib >=1 outlet (0 ditolak)', function () {
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => 'Y', 'email' => 'y@apique.id', 'role' => 'ops', 'outlets' => [],
    ])->assertSessionHasErrors('outlets');
});

it('VALIDASI: email duplikat & nama kosong ditolak', function () {
    User::factory()->create(['email' => 'dupe@apique.id']);
    $this->actingAs(admin())->post(route('admin.users.store'), [
        'name' => '', 'email' => 'dupe@apique.id', 'role' => 'ops', 'outlets' => [120],
    ])->assertSessionHasErrors(['name', 'email']);
});

it('edit user: ubah role & outlet; email immutable', function () {
    $u = tap(User::factory()->create(['name' => 'Lama', 'email' => 'tetap@apique.id']))->assignRole(Permissions::ROLE_OPS);
    $u->outlets()->sync([120]);

    $this->actingAs(admin())->put(route('admin.users.update', $u), [
        'name' => 'Baru', 'email' => 'ganti@apique.id', 'role' => 'head_store', 'outlets' => [121],
    ])->assertRedirect(route('admin.users.index'));

    $u->refresh();
    expect($u->name)->toBe('Baru')
        ->and($u->email)->toBe('tetap@apique.id')          // email TIDAK berubah
        ->and($u->hasRole('head_store'))->toBeTrue()
        ->and($u->hasRole('ops'))->toBeFalse()
        ->and($u->outlets()->pluck('outlets.id_outlet')->all())->toBe([121]);
});

it('nonaktifkan & aktifkan user (toggle status)', function () {
    $u = User::factory()->create(['status' => 'active']);

    $this->actingAs(admin())->put(route('admin.users.toggle', $u));
    expect($u->refresh()->status)->toBe('inactive');

    $this->actingAs(admin())->put(route('admin.users.toggle', $u));
    expect($u->refresh()->status)->toBe('active');
});
