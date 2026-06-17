<?php

use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Revenue\RevenueAdjustmentService;
use App\Modules\Revenue\RevenueReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function fakeVoidFor(array $void): void
{
    Http::fake(function (Request $r) use ($void) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);

        return Http::response(['data' => ($q['status'] ?? '') === 'VOID' ? $void : [], 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

it('wasReported true bila report_run terkonfirmasi terkirim', function () {
    $run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-10', 'status' => 'generated', 'total_sales' => 9000000]);
    $run->deliveries()->create(['id_outlet' => 120, 'channel' => 'hybrid', 'status' => ReportDelivery::CONFIRMED_SENT, 'idempotency_key' => 'k']);

    expect(app(RevenueReconciler::class)->wasReported(120, '2026-06-10'))->toBeTrue()
        ->and(app(RevenueReconciler::class)->wasReported(120, '2026-06-09'))->toBeFalse(); // tak ada run
});

it('wasReported false bila hanya draft (belum terkonfirmasi)', function () {
    $run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-10', 'status' => 'generated']);
    $run->deliveries()->create(['id_outlet' => 120, 'channel' => 'hybrid', 'status' => ReportDelivery::AWAITING_CONFIRMATION, 'idempotency_key' => 'k']);

    expect(app(RevenueReconciler::class)->wasReported(120, '2026-06-10'))->toBeFalse();
});

it('RestateSummary menandai previously_reported per tanggal nota', function () {
    $run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-10', 'status' => 'delivered', 'total_sales' => 9000000]);
    fakeVoidFor([[
        'transaction_number' => 'INV/1', 'status' => 'VOID', 'grand_total' => 200000,
        'created_at' => '2026-06-10 10:00:00', 'approve_refund_void_date' => '2026-06-12 09:00:00', 'void_notes' => 'x',
    ]]);

    $s = app(RevenueAdjustmentService::class)->process(120, '2026-06-12');

    expect($s->byDate['2026-06-10']['previously_reported'])->toBeTrue();
});
