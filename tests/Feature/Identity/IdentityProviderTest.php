<?php

use App\Models\User;
use App\Modules\Identity\Contracts\IdentityProvider;
use App\Modules\Identity\LocalIdentityProvider;
use App\Modules\Identity\OmsIdentity;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeIdentityProvider;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('binding default = LocalIdentityProvider', function () {
    expect(app(IdentityProvider::class))->toBeInstanceOf(LocalIdentityProvider::class);
});

it('LocalIdentityProvider: tamu → current null, check false', function () {
    $provider = app(IdentityProvider::class);

    expect($provider->current())->toBeNull()
        ->and($provider->check())->toBeFalse()
        ->and($provider->can(Permissions::REVIEW_SIGNALS))->toBeFalse();
});

it('LocalIdentityProvider: user login → OmsIdentity dgn role & permission', function () {
    $user = tap(User::factory()->create(['name' => 'Head A']))->assignRole(Permissions::ROLE_HEAD_STORE);
    $this->actingAs($user);

    $provider = app(IdentityProvider::class);
    $identity = $provider->current();

    expect($identity)->toBeInstanceOf(OmsIdentity::class)
        ->and($identity->name)->toBe('Head A')
        ->and($identity->hasRole(Permissions::ROLE_HEAD_STORE))->toBeTrue()
        ->and($provider->can(Permissions::APPROVE_AND_SEND))->toBeTrue()
        ->and($provider->can(Permissions::EDIT_MASTER_DATA))->toBeFalse();
});

it('IdentityProvider DAPAT DITUKAR (fake provider, mis. SSO) tanpa ubah domain', function () {
    // simulasikan principal dari sumber non-lokal
    app()->instance(IdentityProvider::class, new FakeIdentityProvider(
        new OmsIdentity(id: 'sso-991', name: 'SSO User', email: 'sso@erp', roles: ['area_manager'],
            permissions: [Permissions::REVIEW_SIGNALS]),
    ));

    $provider = app(IdentityProvider::class);

    expect($provider)->toBeInstanceOf(FakeIdentityProvider::class)
        ->and($provider->check())->toBeTrue()
        ->and($provider->current()->id)->toBe('sso-991')
        ->and($provider->can(Permissions::REVIEW_SIGNALS))->toBeTrue()
        ->and($provider->can(Permissions::APPROVE_AND_SEND))->toBeFalse();
});

it('OmsIdentity TIDAK menyimpan aktor NEVIRA (pemisahan identitas)', function () {
    $identity = new OmsIdentity(id: 7, name: 'Ops', email: null, roles: ['ops'], permissions: []);

    // id OMS, bukan id_cashier NEVIRA; tak ada properti id_cashier/id_role di sini.
    expect(property_exists($identity, 'id_cashier'))->toBeFalse()
        ->and(property_exists($identity, 'id_role'))->toBeFalse()
        ->and($identity->id)->toBe(7);
});
