<?php

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Modules\Delivery\DeliveryDispatcher;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->run = ReportRun::create([
        'id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated',
        'payload_text' => 'Laporan harian Kemang.',
    ]);
    $this->target = DeliveryTarget::factory()->create([
        'id_outlet' => 120, 'investor_label' => 'Pak Andre', 'deliver_mode' => 'assisted',
        'group_id' => 'GROUP-1', 'whatsapp_account_id' => WhatsappAccount::factory()->create()->id,
    ]);
    config(['whatsapp.base_url' => 'https://graph.facebook.com', 'whatsapp.api_version' => 'v21.0',
        'whatsapp.phone_number_id' => '999', 'whatsapp.default_token' => 'tok', 'whatsapp.template.name' => 'laporan_harian']);
});

function headStoreApr(array $outlets = [120]): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_HEAD_STORE); // APPROVE_AND_SEND
    $u->outlets()->sync($outlets);

    return $u;
}

function assistedDraft($run, $target): ReportDelivery
{
    return app(DeliveryDispatcher::class)->dispatch($run, $target); // assisted → awaiting_approval
}

it('dispatch assisted menyiapkan draft awaiting_approval (tak kirim)', function () {
    Http::fake();
    $d = assistedDraft($this->run, $this->target);
    expect($d->channel)->toBe('cloud_api')->and($d->status)->toBe('awaiting_approval');
    Http::assertNothingSent(); // belum kirim apa pun
});

it('Setujui & Kirim (Cloud API hidup) → SENT + POST ke Graph', function () {
    config(['whatsapp.enabled' => true]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
    $d = assistedDraft($this->run, $this->target);

    $this->actingAs(headStoreApr())->put(route('deliveries.approve-send', $d))->assertRedirect();

    expect($d->refresh()->status)->toBe('sent');
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v21.0/999/messages'));
});

it('Setujui & Kirim saat Cloud API mati → fallback hybrid (awaiting_confirmation)', function () {
    config(['whatsapp.enabled' => false]);
    Http::fake();
    $d = assistedDraft($this->run, $this->target);

    $this->actingAs(headStoreApr())->put(route('deliveries.approve-send', $d))->assertRedirect();

    // draft assisted ditandai gagal, dibuat record hybrid menunggu konfirmasi
    expect($d->refresh()->status)->toBe('failed')
        ->and(ReportDelivery::where('report_run_id', $this->run->id)->where('channel', 'hybrid')->where('status', 'awaiting_confirmation')->exists())->toBeTrue();
    Http::assertNothingSent();
});

it('tanpa APPROVE_AND_SEND → 403', function () {
    Http::fake();
    $d = assistedDraft($this->run, $this->target);
    $am = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER);
    $am->outlets()->sync([120]);
    $this->actingAs($am)->put(route('deliveries.approve-send', $d))->assertForbidden();
});

it('di luar scope outlet → 403', function () {
    Http::fake();
    Outlet::factory()->create(['id_outlet' => 999]);
    $d = assistedDraft($this->run, $this->target);
    $this->actingAs(headStoreApr([999]))->put(route('deliveries.approve-send', $d))->assertForbidden();
});

it('bukan draft assisted menunggu (mis. sudah sent) → 422', function () {
    config(['whatsapp.enabled' => true]);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]], 200)]);
    $d = assistedDraft($this->run, $this->target);
    $this->actingAs(headStoreApr())->put(route('deliveries.approve-send', $d)); // → sent

    $this->actingAs(headStoreApr())->put(route('deliveries.approve-send', $d->refresh()))->assertStatus(422);
});
