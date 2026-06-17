<?php

use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
});

function fdoc(array $over = []): FinancialDocument
{
    static $n = 0;
    $n++;

    return FinancialDocument::factory()->create(array_merge([
        'doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'scope' => 'OUTLET',
        'doc_number' => 'DOC-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'status' => 'SUBMITTED',
    ], $over));
}

function staff(string $role, ?int $outlet = null): User
{
    $u = tap(User::factory()->create())->assignRole($role);
    if ($outlet !== null) {
        $u->outlets()->attach($outlet);
    }

    return $u;
}

it('index scoping: Area Manager hanya lihat outletnya', function () {
    $d120 = fdoc(['id_outlet' => 120, 'doc_number' => 'PR-120']);
    $d121 = fdoc(['id_outlet' => 121, 'doc_number' => 'PR-121']);
    $ho = fdoc(['scope' => 'HEAD_OFFICE', 'id_outlet' => null, 'doc_number' => 'PR-HO']);

    $am = staff(Permissions::ROLE_AREA_MANAGER, 120);

    $this->actingAs($am)->get(route('finance.documents.index'))
        ->assertOk()->assertSee('PR-120')->assertDontSee('PR-121')->assertDontSee('PR-HO');
});

it('index: admin lihat semua termasuk Head Office', function () {
    fdoc(['id_outlet' => 120, 'doc_number' => 'PR-120']);
    fdoc(['id_outlet' => 121, 'doc_number' => 'PR-121']);
    fdoc(['scope' => 'HEAD_OFFICE', 'id_outlet' => null, 'doc_number' => 'PR-HO']);

    $this->actingAs(admin())->get(route('finance.documents.index'))
        ->assertOk()->assertSee('PR-120')->assertSee('PR-121')->assertSee('PR-HO');
});

it('filter per jenis & status', function () {
    fdoc(['doc_type' => 'REFUND', 'doc_number' => 'RF-1', 'status' => 'FINAL']);
    fdoc(['doc_type' => 'PAYMENT_REQUEST', 'doc_number' => 'PR-1', 'status' => 'SUBMITTED']);

    $this->actingAs(admin())->get(route('finance.documents.index', ['doc_type' => 'REFUND']))
        ->assertSee('RF-1')->assertDontSee('PR-1');

    $this->actingAs(admin())->get(route('finance.documents.index', ['status' => 'SUBMITTED']))
        ->assertSee('PR-1')->assertDontSee('RF-1');
});

it('show: detail + jejak approval terlihat', function () {
    $doc = fdoc(['id_outlet' => 120, 'doc_number' => 'PR-SHOW']);
    $am = User::factory()->create(['name' => 'Andi AM']);
    DocumentApproval::create(['document_id' => $doc->id, 'level' => 1, 'approver_user_id' => $am->id, 'approver_role' => 'area_manager', 'action' => 'APPROVED', 'acted_at' => now()]);

    $this->actingAs(admin())->get(route('finance.documents.show', $doc))
        ->assertOk()->assertSee('PR-SHOW')->assertSee('Andi AM')->assertSee('Jejak Approval');
});

it('show: Area Manager tak boleh lihat dokumen outlet lain (403)', function () {
    $doc = fdoc(['id_outlet' => 121, 'doc_number' => 'PR-OTHER']);
    $am = staff(Permissions::ROLE_AREA_MANAGER, 120); // hanya 120

    $this->actingAs($am)->get(route('finance.documents.show', $doc))->assertForbidden();
});

it('rute daftar/show di belakang middleware auth', function () {
    // App tak punya rute "login" (auth internal); cukup pastikan route terdaftar dgn middleware auth.
    $route = app('router')->getRoutes()->getByName('finance.documents.index');
    expect($route)->not->toBeNull()->and($route->gatherMiddleware())->toContain('auth');
});
