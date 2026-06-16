<?php

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->outlet = Outlet::factory()->create([
        'id_outlet' => 120, 'name' => 'Fatmawati', 'report_time' => '21:00', 'active' => true,
    ]);
});

function admin(): User
{
    return tap(User::factory()->create())->assignRole(Permissions::ROLE_ADMIN); // punya master_data.edit
}

function validPayload(array $override = []): array
{
    return array_merge([
        'active' => '1',
        'report_time' => '21:00',
        'checkpoints' => [['hour' => 11, 'threshold' => 50], ['hour' => 14, 'threshold' => 40]],
        'operating_hours' => [['weekday' => 1, 'open' => '10:00', 'close' => '21:00']],
        'holidays' => [['date' => '2026-12-25', 'note' => 'Natal']],
    ], $override);
}

it('menolak akses tanpa permission master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);

    $this->actingAs($ops)->get(route('admin.outlets.edit', $this->outlet))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.outlets.update', $this->outlet), validPayload())->assertForbidden();
});

it('admin melihat form dengan field utama', function () {
    $res = $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet));

    $res->assertOk()
        ->assertSee('Edit Outlet')
        ->assertSee('Jam laporan harian (WIB)')
        ->assertSee('Jam Cek Outlet-Diam')
        ->assertSee('Jam Operasional')
        ->assertSee('Hari Libur');
});

it('STATE outlet baru: baseline belum ada → tampil catatan', function () {
    $res = $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet));
    $res->assertSee('Baseline transaksi belum tersedia');

    OutletBaseline::create(['id_outlet' => 120, 'checkpoint_hour' => 11, 'avg_txn' => 5, 'sample_days' => 30]);
    $res2 = $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet));
    $res2->assertDontSee('Baseline transaksi belum tersedia');
});

it('STATE tersimpan: menyimpan status, jam laporan, checkpoint, jam operasional, libur', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'active' => '0', 'report_time' => '20:30',
    ]));

    $res->assertRedirect(route('admin.outlets.edit', $this->outlet))->assertSessionHas('status');

    $this->outlet->refresh();
    expect($this->outlet->active)->toBeFalse();
    expect((string) $this->outlet->report_time)->toContain('20:30');
    expect($this->outlet->checkpoints()->count())->toBe(2);
    expect($this->outlet->checkpoints()->where('checkpoint_hour', 11)->first()->threshold_pct)->toBe(50);
    expect($this->outlet->operatingHours()->count())->toBe(1);
    expect($this->outlet->holidays()->first()->note)->toBe('Natal');
});

it('checkpoint DINAMIS: update mengganti penuh (hapus yang lama)', function () {
    // seed 3 checkpoint lama
    foreach ([9, 12, 15] as $h) {
        $this->outlet->checkpoints()->create(['checkpoint_hour' => $h, 'threshold_pct' => 50]);
    }

    $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['hour' => 17, 'threshold' => 30]], // tinggal 1
    ]));

    expect($this->outlet->checkpoints()->count())->toBe(1)
        ->and($this->outlet->checkpoints()->first()->checkpoint_hour)->toBe(17);
});

it('VALIDASI: jam operasional tumpang tindih ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'operating_hours' => [
            ['weekday' => 1, 'open' => '10:00', 'close' => '14:00'],
            ['weekday' => 1, 'open' => '13:00', 'close' => '18:00'], // overlap
        ],
    ]));

    $res->assertSessionHasErrors('operating_hours');
    expect($this->outlet->operatingHours()->count())->toBe(0); // tak tersimpan
});

it('VALIDASI: jam tutup <= buka ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'operating_hours' => [['weekday' => 1, 'open' => '21:00', 'close' => '10:00']],
    ]));

    $res->assertSessionHasErrors('operating_hours.0.close');
});

it('VALIDASI: jam checkpoint duplikat ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['hour' => 11, 'threshold' => 50], ['hour' => 11, 'threshold' => 40]],
    ]));

    $res->assertSessionHasErrors('checkpoints');
});

it('VALIDASI: ambang di luar 0–100 ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['hour' => 11, 'threshold' => 150]],
    ]));

    $res->assertSessionHasErrors('checkpoints.0.threshold');
});
