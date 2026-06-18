<?php

use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'LW Kemang']);
    Outlet::factory()->create(['id_outlet' => 999, 'name' => 'LW Lain']);
});

function headStorePreview(array $outlets = [120]): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_HEAD_STORE); // APPROVE_AND_SEND
    $u->outlets()->sync($outlets);

    return $u;
}

function runWithHybridDraft(int $idOutlet = 120): ReportRun
{
    $run = ReportRun::create([
        'id_outlet' => $idOutlet, 'report_date' => '2026-06-15', 'status' => 'READY',
        'payload_text' => "Laporan harian LW Kemang\nTotal: Rp1.000.000", 'total_sales' => 1000000,
        'realized' => 800000, 'receivable' => 200000, 'txn_count' => 42,
    ]);
    ReportDelivery::create([
        'report_run_id' => $run->id, 'id_outlet' => $idOutlet, 'channel' => 'hybrid',
        'status' => ReportDelivery::AWAITING_CONFIRMATION, 'idempotency_key' => 'k-'.$idOutlet,
    ]);

    return $run;
}

it('index hanya menampilkan run dalam scope outlet', function () {
    runWithHybridDraft(120);
    runWithHybridDraft(999);

    $this->actingAs(headStorePreview())->get(route('reports.index'))->assertOk()
        ->assertSee('LW Kemang')->assertDontSee('LW Lain');
});

it('preview menampilkan isi pesan + KPI', function () {
    $run = runWithHybridDraft();
    $this->actingAs(headStorePreview())->get(route('reports.show', $run))->assertOk()
        ->assertSee('Laporan harian LW Kemang')
        ->assertSee('Rp1.000.000')
        ->assertSee('42');
});

it('Head Store melihat tombol "Sudah saya kirim" utk draft hybrid', function () {
    $run = runWithHybridDraft();
    $this->actingAs(headStorePreview())->get(route('reports.show', $run))->assertSee('Sudah saya kirim');
});

it('reviewer tanpa APPROVE_AND_SEND tak melihat tombol kirim', function () {
    $run = runWithHybridDraft();
    $am = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER); // tanpa APPROVE_AND_SEND
    $am->outlets()->sync([120]);
    $this->actingAs($am)->get(route('reports.show', $run))->assertOk()->assertDontSee('Sudah saya kirim');
});

it('tolak preview run di luar scope (403)', function () {
    $run = runWithHybridDraft(999);
    $this->actingAs(headStorePreview([120]))->get(route('reports.show', $run))->assertForbidden();
});

it('konfirmasi kirim → status confirmed_sent (OPS-302)', function () {
    $run = runWithHybridDraft();
    $delivery = $run->deliveries()->first();

    $this->actingAs(headStorePreview())->put(route('deliveries.confirm', $delivery))->assertRedirect();
    expect($delivery->refresh()->status)->toBe(ReportDelivery::CONFIRMED_SENT)
        ->and($delivery->sent_at)->not->toBeNull();
});
