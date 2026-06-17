<?php

use App\Models\Outlet;
use App\Models\ReviewLog;
use App\Models\SignalEvent;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->signal = SignalEvent::create([
        'id_outlet' => 120, 'type' => 'SELF_APPROVAL', 'severity' => 'high', 'id_cashier' => 181,
        'status' => 'OPEN', 'detected_at' => now(),
        'payload_json' => ['approved_by' => 181, 'requested_by' => 181, 'outcome' => 'violation'],
    ]);
});

function reviewer(array $outlets = [120], ?int $neviraId = null): User
{
    $u = tap(User::factory()->create(['nevira_user_id' => $neviraId]))->assignRole(Permissions::ROLE_AREA_MANAGER);
    $u->outlets()->sync($outlets);

    return $u; // area_manager punya REVIEW_SIGNALS
}

it('tinjauan mencatat review_log + ubah status sinyal', function () {
    $this->actingAs(reviewer())
        ->post(route('signals.review', $this->signal), ['outcome' => 'ditindaklanjuti', 'note' => 'Sudah dicek ke kasir.'])
        ->assertRedirect();

    expect($this->signal->refresh()->status)->toBe('REVIEWED');
    $log = ReviewLog::first();
    expect($log->subject_type)->toBe('signal')
        ->and($log->subject_id)->toBe($this->signal->id)
        ->and($log->note)->toBe('Sudah dicek ke kasir.')
        ->and($log->reviewed_at)->not->toBeNull();
});

it('outcome wajar → status DISMISSED', function () {
    $this->actingAs(reviewer())->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => 'Sah, ≥ Kepala Toko.']);
    expect($this->signal->refresh()->status)->toBe('DISMISSED');
});

it('catatan WAJIB', function () {
    $this->actingAs(reviewer())->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => ''])
        ->assertSessionHasErrors('note');
    expect($this->signal->refresh()->status)->toBe('OPEN');
});

it('REVIEWER ≠ SUBJEK: user tertaut aktor subjek (181) → 403 (eskalasi)', function () {
    $this->actingAs(reviewer([120], neviraId: 181))
        ->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => 'menutup sendiri'])
        ->assertForbidden();
    expect($this->signal->refresh()->status)->toBe('OPEN');
});

it('tanpa permission REVIEW_SIGNALS → 403 (investor/lainnya)', function () {
    // user tanpa role apa pun
    $u = tap(User::factory()->create())->syncRoles([]);
    $u->outlets()->sync([120]);
    $this->actingAs($u)->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => 'x'])->assertForbidden();
});

it('scoping: reviewer outlet lain → 403', function () {
    Outlet::factory()->create(['id_outlet' => 121]);
    $this->actingAs(reviewer([121]))->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => 'x'])->assertForbidden();
});

it('review_log append-only (tak bisa diubah/dihapus)', function () {
    $this->actingAs(reviewer())->post(route('signals.review', $this->signal), ['outcome' => 'wajar', 'note' => 'sudah ditinjau']);

    expect(fn () => ReviewLog::first()->update(['note' => 'diubah']))->toThrow(RuntimeException::class);
    expect(fn () => ReviewLog::first()->delete())->toThrow(RuntimeException::class);
    expect(ReviewLog::first()->note)->toBe('sudah ditinjau'); // tetap utuh
});
