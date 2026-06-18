<?php

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Models\WhatsappAccount;
use App\Modules\Delivery\DeliveryDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->outlet = Outlet::factory()->create(['id_outlet' => 120]);
    $this->run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated']);
});

function target(string $mode, WhatsappAccount $acct): DeliveryTarget
{
    return DeliveryTarget::factory()->create([
        'id_outlet' => 120, 'investor_label' => 'Pak Andre',
        'whatsapp_account_id' => $acct->id, 'deliver_mode' => $mode,
    ]);
}

it('hybrid → draft ke Head Store, status awaiting_confirmation, idempoten', function () {
    $t = target('hybrid', WhatsappAccount::factory()->create());

    $d1 = app(DeliveryDispatcher::class)->dispatch($this->run, $t);
    $d2 = app(DeliveryDispatcher::class)->dispatch($this->run, $t);

    expect($d1->channel)->toBe('hybrid')
        ->and($d1->status)->toBe('awaiting_confirmation')
        ->and($d1->id)->toBe($d2->id)                                   // idempoten
        ->and(ReportDelivery::where('report_run_id', $this->run->id)->count())->toBe(1);
});

it('assisted tapi OBA belum siap → effectiveMode hybrid → kirim hybrid', function () {
    $t = target('assisted', WhatsappAccount::factory()->obaNone()->create());

    $d = app(DeliveryDispatcher::class)->dispatch($this->run, $t);
    expect($d->channel)->toBe('hybrid');
});

it('assisted + OBA siap → draft awaiting_approval (TIDAK auto-kirim, OPS-304)', function () {
    $t = target('assisted', WhatsappAccount::factory()->create()); // oba active default → obaReady

    $d = app(DeliveryDispatcher::class)->dispatch($this->run, $t);

    // App TIDAK kirim sendiri; tunggu Head Store "Setujui & Kirim".
    expect($d->channel)->toBe('cloud_api')
        ->and($d->status)->toBe('awaiting_approval');
});

it('full_auto + OBA siap → Cloud API gagal (disabled) → fallback hybrid + alert', function () {
    $logged = '';
    Log::listen(function ($e) use (&$logged) { $logged .= ' '.$e->message; });

    $t = target('full_auto', WhatsappAccount::factory()->create()); // oba active; whatsapp.enabled default false

    $d = app(DeliveryDispatcher::class)->dispatch($this->run, $t);

    expect($d->channel)->toBe('hybrid');                  // fallback
    expect($logged)->toContain('delivery.fallback_hybrid'); // alert, bukan kegagalan diam-diam
});

it('tepat satu channel aktif per (report_run, channel) per hari', function () {
    $t = target('hybrid', WhatsappAccount::factory()->create());
    app(DeliveryDispatcher::class)->dispatch($this->run, $t);

    // channel hybrid kedua utk run sama → ditolak unik (idempotency DB-level)
    expect(fn () => ReportDelivery::create([
        'report_run_id' => $this->run->id, 'id_outlet' => 120, 'channel' => 'hybrid',
        'status' => 'sent', 'idempotency_key' => 'dup',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
