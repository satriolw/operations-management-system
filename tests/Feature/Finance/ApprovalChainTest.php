<?php

use App\Models\ApprovalChain;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.approval-chains.index'))->assertForbidden();
    $this->actingAs($ops)->post(route('admin.approval-chains.store'), [])->assertForbidden();
});

it('role Modul 2 (operations_manager, head_of_operations) tersedia', function () {
    expect(Role::where('name', Permissions::ROLE_OPERATIONS_MANAGER)->exists())->toBeTrue()
        ->and(Role::where('name', Permissions::ROLE_HEAD_OF_OPERATIONS)->exists())->toBeTrue();
});

it('index render 200', function () {
    $this->actingAs(admin())->get(route('admin.approval-chains.index'))
        ->assertOk()->assertViewIs('admin.approval-chains.index');
});

it('admin menambah level rantai (role-based)', function () {
    $this->actingAs(admin())->post(route('admin.approval-chains.store'), [
        'doc_type' => null, 'amount_band' => 'LOW', 'scope' => 'OUTLET', 'level' => 1,
        'approver_role' => Permissions::ROLE_AREA_MANAGER,
    ])->assertRedirect(route('admin.approval-chains.index'));

    $c = ApprovalChain::first();
    expect($c->amount_band)->toBe('LOW')->and($c->approver_role)->toBe('area_manager')->and($c->level)->toBe(1);
});

it('mendukung approver berbasis user (pin user spesifik)', function () {
    $u = User::factory()->create();
    $this->actingAs(admin())->post(route('admin.approval-chains.store'), [
        'amount_band' => 'HIGH', 'scope' => 'OUTLET', 'level' => 2, 'approver_user_id' => $u->id,
    ])->assertRedirect();

    expect(ApprovalChain::first()->approver_user_id)->toBe($u->id);
});

it('tolak level tanpa role MAUPUN user (≥1 wajib)', function () {
    $this->actingAs(admin())->post(route('admin.approval-chains.store'), [
        'amount_band' => 'LOW', 'scope' => 'OUTLET', 'level' => 1,
    ])->assertSessionHasErrors('approver_role');
    expect(ApprovalChain::count())->toBe(0);
});

it('tolak band/scope tak valid', function () {
    $this->actingAs(admin())->post(route('admin.approval-chains.store'), [
        'amount_band' => 'MEDIUM', 'scope' => 'OUTLET', 'level' => 1, 'approver_role' => Permissions::ROLE_AREA_MANAGER,
    ])->assertSessionHasErrors('amount_band');
});

it('update & destroy level', function () {
    $c = ApprovalChain::factory()->create(['level' => 1, 'approver_role' => Permissions::ROLE_AREA_MANAGER]);

    $this->actingAs(admin())->put(route('admin.approval-chains.update', $c), [
        'amount_band' => 'LOW', 'scope' => 'OUTLET', 'level' => 1, 'approver_role' => Permissions::ROLE_OPERATIONS_MANAGER,
    ])->assertRedirect();
    expect($c->refresh()->approver_role)->toBe('operations_manager');

    $this->actingAs(admin())->delete(route('admin.approval-chains.destroy', $c))->assertRedirect();
    expect(ApprovalChain::count())->toBe(0);
});

it('seeder default: LOW=AM→OM, HIGH=OM→HoO (OUTLET & HEAD_OFFICE), idempoten', function () {
    $this->seed(\Database\Seeders\ApprovalChainSeeder::class);
    $this->seed(\Database\Seeders\ApprovalChainSeeder::class); // idempoten

    expect(ApprovalChain::count())->toBe(8); // 2 band × 2 level × 2 scope

    $low = ApprovalChain::where(['scope' => 'OUTLET', 'amount_band' => 'LOW'])->orderBy('level')->pluck('approver_role')->all();
    $high = ApprovalChain::where(['scope' => 'OUTLET', 'amount_band' => 'HIGH'])->orderBy('level')->pluck('approver_role')->all();
    expect($low)->toBe(['area_manager', 'operations_manager'])
        ->and($high)->toBe(['operations_manager', 'head_of_operations']);
});
