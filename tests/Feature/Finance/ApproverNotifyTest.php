<?php

use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Finance\ApprovalEngine;
use App\Modules\Finance\ApproverNotifier;
use App\Modules\Identity\Permissions;
use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(\Database\Seeders\ApprovalChainSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->engine = app(ApprovalEngine::class);
});

function nuser(string $role, ?int $outlet = null): User
{
    $u = tap(User::factory()->create())->assignRole($role);
    if ($outlet !== null) {
        $u->outlets()->attach($outlet);
    }

    return $u;
}

function ndoc(int $amount, User $req): FinancialDocument
{
    return FinancialDocument::factory()->create([
        'doc_type' => 'PAYMENT_REQUEST', 'id_outlet' => 120, 'scope' => 'OUTLET',
        'amount' => $amount, 'requester_user_id' => $req->id, 'status' => 'DRAFT',
    ]);
}

it('submit → notifikasi approver L1 (role + outlet + penerima)', function () {
    Event::fake([OpsAlertRaised::class]);
    $hs = nuser(Permissions::ROLE_HEAD_STORE, 120);
    $am = nuser(Permissions::ROLE_AREA_MANAGER, 120); // calon penerima L1 (LOW)
    $d = ndoc(500000, $hs);

    $this->engine->submit($d, $hs);

    Event::assertDispatched(OpsAlertRaised::class, function (OpsAlertRaised $e) use ($am) {
        return $e->code === 'finance.approval_pending'
            && $e->context['level'] === 1
            && $e->context['approver_role'] === 'area_manager'
            && in_array($am->id, $e->context['recipient_user_ids'], true);
    });
});

it('approve L1 → notifikasi approver L2', function () {
    Event::fake([OpsAlertRaised::class]);
    $hs = nuser(Permissions::ROLE_HEAD_STORE, 120);
    $am = nuser(Permissions::ROLE_AREA_MANAGER, 120);
    nuser(Permissions::ROLE_OPERATIONS_MANAGER);
    $d = ndoc(500000, $hs);

    $this->engine->submit($d, $hs);
    $this->engine->approve($d, $am);

    Event::assertDispatched(OpsAlertRaised::class, fn (OpsAlertRaised $e) => $e->context['level'] === 2 && $e->context['approver_role'] === 'operations_manager');
});

it('approve final → tak ada notifikasi approver berikutnya', function () {
    $hs = nuser(Permissions::ROLE_HEAD_STORE, 120);
    $am = nuser(Permissions::ROLE_AREA_MANAGER, 120);
    $om = nuser(Permissions::ROLE_OPERATIONS_MANAGER);
    $d = ndoc(500000, $hs);
    $this->engine->submit($d, $hs);
    $this->engine->approve($d, $am);

    Event::fake([OpsAlertRaised::class]); // mulai rekam sebelum approve final
    $this->engine->approve($d, $om); // → FINAL

    Event::assertNotDispatched(OpsAlertRaised::class);
});

it('idempoten: notifyNext dipanggil ulang level sama → satu alert (tidak spam)', function () {
    Event::fake([OpsAlertRaised::class]);
    $hs = nuser(Permissions::ROLE_HEAD_STORE, 120);
    nuser(Permissions::ROLE_AREA_MANAGER, 120);
    $d = ndoc(500000, $hs);
    $this->engine->submit($d, $hs); // sudah 1 alert L1

    $notifier = app(ApproverNotifier::class);
    expect($notifier->notifyNext($d))->toBeFalse(); // raiseOnce → tak diangkat lagi

    Event::assertDispatchedTimes(OpsAlertRaised::class, 1);
});

it('dokumen DRAFT/REJECTED → notifyNext tak mengirim', function () {
    $hs = nuser(Permissions::ROLE_HEAD_STORE, 120);
    $draft = ndoc(500000, $hs); // DRAFT
    expect(app(ApproverNotifier::class)->notifyNext($draft))->toBeFalse();
});
