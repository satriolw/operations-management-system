<?php

use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

function userWithRole(string $role): User
{
    return tap(User::factory()->create())->assignRole($role);
}

it('login berhasil dgn kredensial benar, gagal dgn salah', function () {
    $user = User::factory()->create(['password' => Hash::make('rahasia123')]);

    expect(Auth::attempt(['email' => $user->email, 'password' => 'rahasia123']))->toBeTrue();
    expect(Auth::attempt(['email' => $user->email, 'password' => 'salah']))->toBeFalse();
});

it('seeder membuat 4 role + 3 permission sesuai katalog', function () {
    expect(\Spatie\Permission\Models\Role::count())->toBe(4)
        ->and(\Spatie\Permission\Models\Permission::count())->toBe(3);
});

it('gate mengizinkan/menolak aksi sensitif sesuai role', function () {
    $admin = userWithRole(Permissions::ROLE_ADMIN);
    $head = userWithRole(Permissions::ROLE_HEAD_STORE);
    $area = userWithRole(Permissions::ROLE_AREA_MANAGER);
    $ops = userWithRole(Permissions::ROLE_OPS);

    // Setujui & Kirim → admin + head_store saja
    expect($admin->can(Permissions::APPROVE_AND_SEND))->toBeTrue()
        ->and($head->can(Permissions::APPROVE_AND_SEND))->toBeTrue()
        ->and($area->can(Permissions::APPROVE_AND_SEND))->toBeFalse()
        ->and($ops->can(Permissions::APPROVE_AND_SEND))->toBeFalse();

    // Review sinyal → semua role
    expect($head->can(Permissions::REVIEW_SIGNALS))->toBeTrue()
        ->and($area->can(Permissions::REVIEW_SIGNALS))->toBeTrue()
        ->and($ops->can(Permissions::REVIEW_SIGNALS))->toBeTrue();

    // Edit master data → admin saja
    expect($admin->can(Permissions::EDIT_MASTER_DATA))->toBeTrue()
        ->and($head->can(Permissions::EDIT_MASTER_DATA))->toBeFalse()
        ->and($ops->can(Permissions::EDIT_MASTER_DATA))->toBeFalse();
});

it('Gate facade menghormati permission utk user yang sedang login', function () {
    $this->actingAs(userWithRole(Permissions::ROLE_HEAD_STORE));

    expect(Gate::allows(Permissions::APPROVE_AND_SEND))->toBeTrue()
        ->and(Gate::allows(Permissions::EDIT_MASTER_DATA))->toBeFalse();
});
