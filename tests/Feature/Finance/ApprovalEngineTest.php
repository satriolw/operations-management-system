<?php

use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Finance\ApprovalEngine;
use App\Modules\Finance\ChainResolver;
use App\Modules\Finance\Exceptions\ApprovalException;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\ApprovalChainSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->engine = app(ApprovalEngine::class);
});

function userRole(string $role, ?int $outlet = null): User
{
    $u = tap(User::factory()->create())->assignRole($role);
    if ($outlet !== null) {
        $u->outlets()->attach($outlet);
    }

    return $u;
}

function doc(int $amount, User $requester, array $over = []): FinancialDocument
{
    return FinancialDocument::factory()->create(array_merge([
        'doc_type' => 'PAYMENT_REQUEST', 'id_outlet' => 120, 'scope' => 'OUTLET',
        'amount' => $amount, 'requester_user_id' => $requester->id, 'status' => 'DRAFT',
    ], $over));
}

it('happy path LOW: head_store ajukan → AM → OM → FINAL', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120);
    $om = userRole(Permissions::ROLE_OPERATIONS_MANAGER);
    $d = doc(500000, $hs);

    $this->engine->submit($d, $hs);
    expect($d->status)->toBe('SUBMITTED')->and($d->amount_band)->toBe('LOW')->and($d->current_level)->toBe(1);

    $this->engine->approve($d, $am);
    expect($d->status)->toBe('APPROVED_L1')->and($d->current_level)->toBe(2);

    $this->engine->approve($d, $om);
    expect($d->status)->toBe('FINAL')->and($d->finalized_at)->not->toBeNull();
    expect(DocumentApproval::where('document_id', $d->id)->where('action', 'APPROVED')->count())->toBe(2);
});

it('happy path HIGH: band ≥1jt → OM → HoO', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $om = userRole(Permissions::ROLE_OPERATIONS_MANAGER);
    $hoo = userRole(Permissions::ROLE_HEAD_OF_OPERATIONS);
    $d = doc(2500000, $hs);

    $this->engine->submit($d, $hs);
    expect($d->amount_band)->toBe('HIGH');
    $this->engine->approve($d, $om);
    $this->engine->approve($d, $hoo);
    expect($d->fresh()->status)->toBe('FINAL');
});

it('reject simpan catatan wajib → REJECTED', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120);
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);

    $this->engine->reject($d, $am, 'Bukti kurang');
    expect($d->status)->toBe('REJECTED')
        ->and(DocumentApproval::where('document_id', $d->id)->where('action', 'REJECTED')->first()->note)->toBe('Bukti kurang');
});

it('reject tanpa catatan → ditolak', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120);
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);

    expect(fn () => $this->engine->reject($d, $am, '  '))->toThrow(ApprovalException::class);
});

it('reviewer ≠ requester: pengaju tak bisa approve dokumennya', function () {
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120); // AM mengajukan
    $d = doc(500000, $am);
    $this->engine->submit($d, $am);

    expect(fn () => $this->engine->approve($d, $am))->toThrow(ApprovalException::class);
});

it('tak boleh loncat level: approver level-2 approve saat level-1', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $om = userRole(Permissions::ROLE_OPERATIONS_MANAGER); // L2 utk LOW
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);

    // level berjalan = 1 (AM). OM (level 2) tak boleh approve dulu.
    expect(fn () => $this->engine->approve($d, $om))->toThrow(ApprovalException::class);
});

it('approver salah role → ditolak', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $other = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);

    expect(fn () => $this->engine->approve($d, $other))->toThrow(ApprovalException::class);
});

it('FINAL immutable: tak bisa approve/reject lagi', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120);
    $om = userRole(Permissions::ROLE_OPERATIONS_MANAGER);
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);
    $this->engine->approve($d, $am);
    $this->engine->approve($d, $om); // FINAL

    expect(fn () => $this->engine->approve($d, $om))->toThrow(ApprovalException::class);
    expect(fn () => $this->engine->reject($d, $om, 'x'))->toThrow(ApprovalException::class);
});

it('submit dari status non-DRAFT → invalid', function () {
    $hs = userRole(Permissions::ROLE_HEAD_STORE, 120);
    $d = doc(500000, $hs);
    $this->engine->submit($d, $hs);
    expect(fn () => $this->engine->submit($d, $hs))->toThrow(ApprovalException::class);
});

it('skip+geser: pengaju Area Manager → rantai LOW digeser ke OM→HoO', function () {
    $am = userRole(Permissions::ROLE_AREA_MANAGER, 120); // pengaju menempati L1 LOW
    $d = doc(500000, $am);

    $chain = app(ChainResolver::class)->resolve($d);
    expect(collect($chain)->pluck('role')->all())->toBe(['operations_manager', 'head_of_operations']);
});

it('skip+geser: pengaju Operations Manager (HIGH) → tinggal 1 approver (HoO)', function () {
    $om = userRole(Permissions::ROLE_OPERATIONS_MANAGER);
    $d = doc(2500000, $om, ['id_outlet' => null, 'scope' => 'HEAD_OFFICE']);

    $chain = app(ChainResolver::class)->resolve($d);
    expect(collect($chain)->pluck('role')->all())->toBe(['head_of_operations']);
});
