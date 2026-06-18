<?php

use App\Models\Outlet;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('route login & dashboard terdaftar (route:list)', function () {
    expect(app('router')->getRoutes()->getByName('login'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('dashboard'))->not->toBeNull()
        ->and(app('router')->getRoutes()->getByName('logout'))->not->toBeNull();
});

it("'/' → login bila belum auth, dashboard bila auth", function () {
    $this->get('/')->assertRedirect(route('login'));

    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);
    $this->actingAs($u)->get('/')->assertRedirect(route('dashboard'));
});

it('GET login render 200 utk tamu', function () {
    $this->get(route('login'))->assertOk()->assertSee('Masuk ke OMS');
});

it('login sukses → redirect dashboard + terautentikasi', function () {
    $u = tap(User::factory()->create(['email' => 'a@lessworry.id', 'password' => Hash::make('rahasia123'), 'status' => 'active']))->assignRole(Permissions::ROLE_ADMIN);

    $this->post(route('login'), ['email' => 'a@lessworry.id', 'password' => 'rahasia123'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($u);
});

it('login gagal (sandi salah) → kembali dgn error, tetap tamu', function () {
    User::factory()->create(['email' => 'a@lessworry.id', 'password' => Hash::make('benar123'), 'status' => 'active']);

    $this->from(route('login'))->post(route('login'), ['email' => 'a@lessworry.id', 'password' => 'salah'])
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('akun nonaktif ditolak login', function () {
    User::factory()->create(['email' => 'x@lessworry.id', 'password' => Hash::make('rahasia123'), 'status' => 'inactive']);

    $this->post(route('login'), ['email' => 'x@lessworry.id', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('dashboard butuh auth: tamu → redirect login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('dashboard kosong → "Operasional bersih"', function () {
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);
    $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Operasional bersih');
});

it('logout → redirect login + jadi tamu', function () {
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);
    $this->actingAs($u)->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('nav ROLE-AWARE: admin lihat menu master data; area_manager tidak', function () {
    $admin = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertSee('Kapasitas')->assertSee('Saldo NEVIRA')->assertSee('Dokumen Keuangan');

    $am = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER);
    $this->actingAs($am)->get(route('dashboard'))->assertOk()
        ->assertDontSee('Saldo NEVIRA')->assertSee('Dokumen Keuangan')->assertSee('Leaderboard');
});

it('GET daftar outlet (index) tersedia utk shell', function () {
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Fatmawati']);
    $admin = tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN);

    $this->actingAs($admin)->get(route('admin.outlets.index'))->assertOk()->assertSee('Fatmawati');
});
