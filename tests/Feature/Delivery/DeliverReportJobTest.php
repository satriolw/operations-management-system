<?php

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Models\WhatsappAccount;
use App\Modules\Delivery\Jobs\DeliverReportJob;
use App\Support\Observability\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']);
    Outlet::factory()->create(['id_outlet' => 120]);
    $this->run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated']);
    $this->target = DeliveryTarget::factory()->create([
        'id_outlet' => 120, 'deliver_mode' => 'hybrid', 'whatsapp_account_id' => WhatsappAccount::factory(),
    ]);
});

it('kirim sukses → report_delivery tercatat + metrik reports_delivered', function () {
    DeliverReportJob::dispatchSync($this->run->id, $this->target->id);

    expect(ReportDelivery::where('report_run_id', $this->run->id)->count())->toBe(1)
        ->and(ReportDelivery::first()->channel)->toBe('hybrid')
        ->and(Metrics::get(Metrics::REPORTS_DELIVERED))->toBe(1);
});

it('idempoten: dua kali kirim → satu report_delivery', function () {
    DeliverReportJob::dispatchSync($this->run->id, $this->target->id);
    DeliverReportJob::dispatchSync($this->run->id, $this->target->id);

    expect(ReportDelivery::where('report_run_id', $this->run->id)->count())->toBe(1);
});

it('retryable: ShouldQueue, tries=3, backoff bertingkat', function () {
    $job = new DeliverReportJob($this->run->id, $this->target->id);
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900]);
});

it('gagal final → alert ops + metrik reports_failed (bukan silent)', function () {
    $logged = '';
    Log::listen(function ($e) use (&$logged) { $logged .= ' '.$e->message; });

    (new DeliverReportJob($this->run->id, $this->target->id))->failed(new RuntimeException('SESSION_LOGGED_OUT'));

    expect(Metrics::get(Metrics::REPORTS_FAILED))->toBe(1)
        ->and($logged)->toContain('delivery.failed');
});
