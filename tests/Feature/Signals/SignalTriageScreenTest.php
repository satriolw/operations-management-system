<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'LW Kemang']);
    Outlet::factory()->create(['id_outlet' => 999, 'name' => 'LW Lain']);
});

function triager(array $outlets = [120]): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER); // punya REVIEW_SIGNALS
    $u->outlets()->sync($outlets);

    return $u;
}

it('menolak akses layar tanpa permission REVIEW_SIGNALS (403)', function () {
    // operations_manager: scope all tapi TANPA permission review
    $om = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPERATIONS_MANAGER);
    $this->actingAs($om)->get(route('signals.index'))->assertForbidden();
});

it('hanya menampilkan sinyal dalam scope outlet reviewer', function () {
    SignalEvent::create(['id_outlet' => 120, 'type' => 'SILENT_OUTLET', 'severity' => 'high', 'status' => 'OPEN', 'detected_at' => now(), 'payload_json' => []]);
    SignalEvent::create(['id_outlet' => 999, 'type' => 'PROMO_LEAKAGE', 'severity' => 'low', 'status' => 'OPEN', 'detected_at' => now(), 'payload_json' => []]);

    $this->actingAs(triager())->get(route('signals.index'))->assertOk()
        ->assertSee('SILENT_OUTLET')
        ->assertDontSee('PROMO_LEAKAGE'); // outlet 999 di luar scope
});

it('menampilkan form tinjau inline utk sinyal OPEN; nilai metadata payload (tanpa PII)', function () {
    SignalEvent::create(['id_outlet' => 120, 'type' => 'OFF_PRICE_SALE', 'severity' => 'high', 'status' => 'OPEN',
        'ref_transaction_number' => 'TRX-9', 'detected_at' => now(), 'payload_json' => ['selisih' => 15000]]);

    $this->actingAs(triager())->get(route('signals.index'))
        ->assertSee('Catat tinjauan')         // form aksi muncul (boleh review)
        ->assertSee('TRX-9')
        ->assertSee('15000');
});

it('sinyal non-OPEN tak menampilkan form tinjau', function () {
    SignalEvent::create(['id_outlet' => 120, 'type' => 'OVERLOAD', 'severity' => 'low', 'status' => 'DISMISSED', 'detected_at' => now(), 'payload_json' => []]);

    $this->actingAs(triager())->get(route('signals.index'))->assertOk()
        ->assertSee('OVERLOAD')->assertDontSee('Catat tinjauan');
});

it('filter status mempersempit hasil', function () {
    SignalEvent::create(['id_outlet' => 120, 'type' => 'LATE_ORDER', 'severity' => 'high', 'status' => 'OPEN', 'ref_transaction_number' => 'OPEN-NOTA', 'detected_at' => now(), 'payload_json' => []]);
    SignalEvent::create(['id_outlet' => 120, 'type' => 'AGING_PIUTANG', 'severity' => 'low', 'status' => 'REVIEWED', 'ref_transaction_number' => 'DONE-NOTA', 'detected_at' => now(), 'payload_json' => []]);

    // AGING_PIUTANG tetap muncul di dropdown jenis; cek baris hasil lewat penanda nota unik.
    $this->actingAs(triager())->get(route('signals.index', ['status' => 'OPEN']))
        ->assertSee('OPEN-NOTA')->assertDontSee('DONE-NOTA');
});
