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
    Outlet::factory()->create(['id_outlet' => 120]);
    $run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated']);
    $this->delivery = ReportDelivery::create([
        'report_run_id' => $run->id, 'id_outlet' => 120, 'channel' => 'hybrid',
        'status' => ReportDelivery::AWAITING_CONFIRMATION, 'idempotency_key' => 'k1',
    ]);
});

function headStore(array $outlets = [120]): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_HEAD_STORE);
    $u->outlets()->sync($outlets);

    return $u;
}

it('status awal = draft (awaiting), belum terverifikasi ke investor', function () {
    expect($this->delivery->isAwaitingConfirmation())->toBeTrue()
        ->and($this->delivery->isConfirmedDelivered())->toBeFalse();
});

it('"Sudah saya kirim" → confirmed_sent + sent_at, oleh Head Store outlet itu', function () {
    $this->actingAs(headStore([120]))
        ->put(route('deliveries.confirm', $this->delivery))
        ->assertRedirect();

    $this->delivery->refresh();
    expect($this->delivery->status)->toBe(ReportDelivery::CONFIRMED_SENT)
        ->and($this->delivery->isConfirmedDelivered())->toBeTrue()
        ->and($this->delivery->sent_at)->not->toBeNull();
});

it('ditolak: user tanpa permission APPROVE_AND_SEND (ops) → 403', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $ops->outlets()->sync([120]);

    $this->actingAs($ops)->put(route('deliveries.confirm', $this->delivery))->assertForbidden();
    expect($this->delivery->refresh()->isAwaitingConfirmation())->toBeTrue();
});

it('ditolak: Head Store outlet lain (scoping OPS-1003) → 403', function () {
    Outlet::factory()->create(['id_outlet' => 121]);
    $this->actingAs(headStore([121]))->put(route('deliveries.confirm', $this->delivery))->assertForbidden();
});

it('tak bisa konfirmasi dua kali (bukan awaiting lagi) → 422', function () {
    $this->delivery->update(['status' => ReportDelivery::CONFIRMED_SENT]);

    $this->actingAs(headStore([120]))->put(route('deliveries.confirm', $this->delivery))->assertStatus(422);
});
