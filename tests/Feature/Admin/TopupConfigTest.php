<?php

use App\Models\NeviraTopupConfig;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('menolak akses tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);

    $this->actingAs($ops)->get(route('admin.topup-config.index'))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.topup-config.update'), [])->assertForbidden();
});

it('index render 200 (default Senin/Kamis dibuat bila belum ada)', function () {
    $this->actingAs(admin())->get(route('admin.topup-config.index'))
        ->assertOk()->assertViewIs('admin.topup-config.index');

    expect(NeviraTopupConfig::current()->weekdays())->toBe([1, 4]); // Senin, Kamis
});

it('current() singleton: tak menggandakan baris', function () {
    NeviraTopupConfig::current();
    NeviraTopupConfig::current();
    expect(NeviraTopupConfig::count())->toBe(1);
});

it('update menyimpan hari pencairan & ambang (configurable tanpa deploy)', function () {
    $this->actingAs(admin())->put(route('admin.topup-config.update'), [
        'disbursement_weekdays' => [1, 4, 5], // tambah Jumat
        'submission_cutoff_lead_hours' => 12,
        'target_ceiling' => 15000000,
        'buffer_days' => 4,
        'warning_runway_days' => 9,
        'critical_runway_days' => 5,
    ])->assertRedirect(route('admin.topup-config.index'));

    $c = NeviraTopupConfig::current();
    expect($c->weekdays())->toBe([1, 4, 5])
        ->and($c->submission_cutoff_lead_hours)->toBe(12)
        ->and($c->warning_runway_days)->toBe(9);
    expect(NeviraTopupConfig::count())->toBe(1);
});

it('weekdays() membersihkan nilai di luar 0..6 & duplikat', function () {
    $c = NeviraTopupConfig::factory()->create(['disbursement_weekdays' => [4, 1, 1, 9, -1, 4]]);
    expect($c->weekdays())->toBe([1, 4]);
});

it('tolak warning < kritis', function () {
    $this->actingAs(admin())->put(route('admin.topup-config.update'), [
        'disbursement_weekdays' => [1, 4],
        'submission_cutoff_lead_hours' => 24, 'target_ceiling' => 0, 'buffer_days' => 3,
        'warning_runway_days' => 4, 'critical_runway_days' => 6, // warning < kritis
    ])->assertSessionHasErrors('warning_runway_days');
});

it('tolak hari pencairan di luar 0..6', function () {
    $this->actingAs(admin())->put(route('admin.topup-config.update'), [
        'disbursement_weekdays' => [7],
        'submission_cutoff_lead_hours' => 24, 'target_ceiling' => 0, 'buffer_days' => 3,
        'warning_runway_days' => 8, 'critical_runway_days' => 5,
    ])->assertSessionHasErrors('disbursement_weekdays.0');
});

it('tolak daftar hari pencairan kosong', function () {
    $this->actingAs(admin())->put(route('admin.topup-config.update'), [
        'disbursement_weekdays' => [],
        'submission_cutoff_lead_hours' => 24, 'target_ceiling' => 0, 'buffer_days' => 3,
        'warning_runway_days' => 8, 'critical_runway_days' => 5,
    ])->assertSessionHasErrors('disbursement_weekdays');
});
