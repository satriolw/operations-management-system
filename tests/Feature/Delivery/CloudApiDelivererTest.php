<?php

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Models\WhatsappAccount;
use App\Modules\Delivery\ApprovedTemplateFit;
use App\Modules\Delivery\CloudApiDeliverer;
use App\Modules\Delivery\DeliveryDispatcher;
use App\Modules\Delivery\Exceptions\DeliveryFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->run = ReportRun::create([
        'id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated',
        'payload_text' => 'Laporan harian Kemang. Total Rp10.000.000.',
    ]);
    // Sandbox config: Cloud API hidup, kredensial fallback token tunggal.
    config([
        'whatsapp.enabled' => true,
        'whatsapp.base_url' => 'https://graph.facebook.com',
        'whatsapp.api_version' => 'v21.0',
        'whatsapp.phone_number_id' => '99887766',
        'whatsapp.default_token' => 'sandbox-token-xyz',
        'whatsapp.template.name' => 'laporan_harian',
        'whatsapp.template.language' => 'id',
        'whatsapp.template.max_param_chars' => 1024,
    ]);
});

function obaTarget(?string $groupId = 'GROUP-1', string $mode = 'assisted'): DeliveryTarget
{
    return DeliveryTarget::factory()->create([
        'id_outlet' => 120, 'investor_label' => 'Pak Andre', 'deliver_mode' => $mode,
        'group_id' => $groupId,
        'whatsapp_account_id' => WhatsappAccount::factory()->create()->id, // oba active default
    ]);
}

it('kirim sukses via Cloud API → status SENT + POST template ke Graph', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200)]);

    $d = app(CloudApiDeliverer::class)->deliver($this->run, obaTarget());

    expect($d->channel)->toBe('cloud_api')->and($d->status)->toBe('sent')->and($d->sent_at)->not->toBeNull();

    Http::assertSent(function (Request $r) {
        $body = $r->data();
        return str_contains($r->url(), '/v21.0/99887766/messages')
            && $r->hasHeader('Authorization', 'Bearer sandbox-token-xyz')
            && $body['to'] === 'GROUP-1'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'laporan_harian'
            && $body['template']['components'][0]['parameters'][0]['text'] === 'Laporan harian Kemang. Total Rp10.000.000.';
    });
});

it('idempoten: kirim kedua tak POST ulang, kembalikan record sama', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
    $t = obaTarget();

    $d1 = app(CloudApiDeliverer::class)->deliver($this->run, $t);
    $d2 = app(CloudApiDeliverer::class)->deliver($this->run, $t);

    expect($d1->id)->toBe($d2->id)
        ->and(ReportDelivery::where('channel', 'cloud_api')->count())->toBe(1);
    Http::assertSentCount(1); // tak ada kiriman ganda
});

it('HTTP non-2xx → DeliveryFailed (dispatcher akan fallback hybrid)', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'bad'], 400)]);

    expect(fn () => app(CloudApiDeliverer::class)->deliver($this->run, obaTarget()))
        ->toThrow(DeliveryFailed::class);
    expect(ReportDelivery::where('channel', 'cloud_api')->where('status', 'sent')->count())->toBe(0);
});

it('master switch OFF → DeliveryFailed tanpa memanggil Graph', function () {
    config(['whatsapp.enabled' => false]);
    Http::fake();

    expect(fn () => app(CloudApiDeliverer::class)->deliver($this->run, obaTarget()))
        ->toThrow(DeliveryFailed::class);
    Http::assertNothingSent();
});

it('group_id kosong → DeliveryFailed', function () {
    Http::fake();
    expect(fn () => app(CloudApiDeliverer::class)->deliver($this->run, obaTarget(groupId: null)))
        ->toThrow(DeliveryFailed::class);
    Http::assertNothingSent();
});

it('konten melebihi batas approved template → DeliveryFailed (R7)', function () {
    config(['whatsapp.template.max_param_chars' => 20]);
    Http::fake();

    expect(fn () => app(CloudApiDeliverer::class)->deliver($this->run, obaTarget()))
        ->toThrow(DeliveryFailed::class);
    Http::assertNothingSent();
});

it('kredensial tak ter-resolve (tanpa default_token & map) → DeliveryFailed', function () {
    config(['whatsapp.default_token' => null, 'whatsapp.credentials' => []]);
    Http::fake();

    expect(fn () => app(CloudApiDeliverer::class)->deliver($this->run, obaTarget()))
        ->toThrow(DeliveryFailed::class);
    Http::assertNothingSent();
});

it('end-to-end via DeliveryDispatcher: full_auto + OBA + sandbox → terkirim cloud_api', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.9']]], 200)]);

    $d = app(DeliveryDispatcher::class)->dispatch($this->run, obaTarget(mode: 'full_auto'));
    expect($d->channel)->toBe('cloud_api')->and($d->status)->toBe('sent');
});

it('ApprovedTemplateFit: kosong & kepanjangan tak muat; normal muat', function () {
    config(['whatsapp.template.max_param_chars' => 10]);
    $fit = new ApprovedTemplateFit();
    expect($fit->fits(''))->toBeFalse()
        ->and($fit->fits('12345678901'))->toBeFalse() // 11 > 10
        ->and($fit->fits('halo'))->toBeTrue();
});
