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
        'id_outlet' => 120, 'name' => 'Kemang', 'report_time' => '20:30', 'active' => true,
        'silent_threshold_pct' => 40, 'comparison_basis' => 'avg_14d',
    ]);
});

function validPayload(array $override = []): array
{
    return array_merge([
        'active' => '1',
        'report_time' => '20:30',
        'silent_threshold_pct' => 40,
        'comparison_basis' => 'avg_14d',
        'checkpoints' => [['time' => '11:00'], ['time' => '14:00'], ['time' => '17:00']],
        'operating_hours' => [
            ['weekday' => 1, 'is_closed' => '0', 'open' => '09:00', 'close' => '21:00'],
            ['weekday' => 0, 'is_closed' => '1'],
        ],
        'holidays' => [['date' => '2026-08-17', 'note' => 'HUT RI']],
    ], $override);
}

it('menolak akses tanpa permission master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.outlets.edit', $this->outlet))->assertForbidden();
    $this->actingAs($ops)->put(route('admin.outlets.update', $this->outlet), validPayload())->assertForbidden();
});

it('admin melihat form + komponen desain (cards, sidebar, savebar)', function () {
    $res = $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet));
    $res->assertOk()
        ->assertSee('Edit Outlet')
        ->assertSee('Identitas Outlet')
        ->assertSee('Deteksi Outlet-Diam')
        ->assertSee('Jam Operasional')
        ->assertSee('Hari libur khusus')
        ->assertSee('Ambang outlet-diam');
});

it('STATE outlet baru: baseline belum ada → catatan + class on', function () {
    $res = $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet));
    $res->assertSee('Baseline belum tersedia')->assertSee('baseline on', false);

    OutletBaseline::create(['id_outlet' => 120, 'checkpoint_hour' => 11, 'avg_txn' => 5, 'sample_days' => 30]);
    $this->actingAs(admin())->get(route('admin.outlets.edit', $this->outlet))
        ->assertDontSee('baseline on', false);
});

it('STATE tersimpan: simpan status, jam laporan, ambang, jam cek, operasional, libur', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'active' => '0', 'report_time' => '21:00', 'silent_threshold_pct' => 55, 'comparison_basis' => 'avg_30d',
    ]));
    $res->assertRedirect(route('admin.outlets.edit', $this->outlet))->assertSessionHas('status');

    $this->outlet->refresh();
    expect($this->outlet->active)->toBeFalse()
        ->and((string) $this->outlet->report_time)->toContain('21:00')
        ->and($this->outlet->silent_threshold_pct)->toBe(55)
        ->and($this->outlet->comparison_basis)->toBe('avg_30d')
        ->and($this->outlet->checkpoints()->count())->toBe(3)
        ->and($this->outlet->operatingHours()->where('weekday', 0)->first()->is_closed)->toBeTrue()
        ->and($this->outlet->operatingHours()->where('weekday', 1)->first()->open_time)->toContain('09:00')
        ->and($this->outlet->holidays()->first()->note)->toBe('HUT RI');
});

it('jam cek DINAMIS: update mengganti penuh (hapus yang lama)', function () {
    foreach (['09:00', '12:00', '15:00'] as $t) {
        $this->outlet->checkpoints()->create(['check_time' => $t]);
    }
    $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['time' => '18:00']],
    ]));
    expect($this->outlet->checkpoints()->count())->toBe(1)
        ->and((string) $this->outlet->checkpoints()->first()->check_time)->toContain('18:00');
});

it('VALIDASI: jam cek tumpang tindih (< 30 menit) ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['time' => '11:00'], ['time' => '11:20']], // 20 menit
    ]));
    $res->assertSessionHasErrors('checkpoints');
    expect($this->outlet->checkpoints()->count())->toBe(0);
});

it('VALIDASI: jam cek tepat 30 menit DITERIMA', function () {
    $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'checkpoints' => [['time' => '11:00'], ['time' => '11:30']],
    ]))->assertSessionDoesntHaveErrors('checkpoints');
});

it('VALIDASI: hari buka dgn jam tutup <= buka ditolak', function () {
    $res = $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'operating_hours' => [['weekday' => 1, 'is_closed' => '0', 'open' => '21:00', 'close' => '09:00']],
    ]));
    $res->assertSessionHasErrors('operating_hours.0.close');
});

it('VALIDASI: ambang di luar 0–100 ditolak', function () {
    $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'silent_threshold_pct' => 150,
    ]))->assertSessionHasErrors('silent_threshold_pct');
});

it('hari tutup tidak butuh jam buka/tutup', function () {
    $this->actingAs(admin())->put(route('admin.outlets.update', $this->outlet), validPayload([
        'operating_hours' => [['weekday' => 0, 'is_closed' => '1']],
    ]))->assertSessionDoesntHaveErrors();
});
