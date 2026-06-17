<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\RevenueAdjustment;
use App\Modules\Revenue\RevenueAdjustmentService;
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

function fakeVR(array $void, array $refund = []): void
{
    Http::fake(function (Request $r) use ($void, $refund) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
        $data = ($q['status'] ?? '') === 'REFUND' ? $refund : (($q['status'] ?? '') === 'VOID' ? $void : []);

        return Http::response(['data' => $data, 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function vr(string $no, string $type, int $amount, string $created): array
{
    return [
        'transaction_number' => $no, 'status' => $type, 'grand_total' => $amount,
        'created_at' => $created, 'approve_refund_void_date' => '2026-06-12 09:00:00',
        'void_notes' => 'salah input', 'refund_notes' => 'komplain', 'id_cashier' => 181,
    ];
}

it('restate revenue lama→baru per tanggal nota (9.200.000 − 430.000 = 8.770.000)', function () {
    ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-10', 'status' => 'delivered', 'total_sales' => 9200000]);
    fakeVR(
        [vr('INV/00119', 'VOID', 180000, '2026-06-10 13:00:00')],
        [vr('INV/00123', 'REFUND', 250000, '2026-06-10 14:00:00')],
    );

    $s = app(RevenueAdjustmentService::class)->process(120, '2026-06-12');

    expect($s->totalCorrection)->toBe(430000)
        ->and($s->byDate['2026-06-10']['old'])->toBe(9200000)
        ->and($s->byDate['2026-06-10']['correction'])->toBe(430000)
        ->and($s->byDate['2026-06-10']['new'])->toBe(8770000)
        ->and($s->byDate['2026-06-10']['count'])->toBe(2);
});

it('persist revenue_adjustments (referensi transaction_number, void & refund)', function () {
    fakeVR(
        [vr('INV/V', 'VOID', 81225, '2026-06-11 10:00:00')],
        [vr('INV/R', 'REFUND', 582560, '2026-06-09 10:00:00')],
    );

    app(RevenueAdjustmentService::class)->process(120, '2026-06-12');

    expect(RevenueAdjustment::count())->toBe(2)
        ->and((int) RevenueAdjustment::where('transaction_number', 'INV/R')->first()->amount)->toBe(582560)
        ->and(RevenueAdjustment::pluck('type')->sort()->values()->all())->toBe(['REFUND', 'VOID']);
});

it('idempoten: process dua kali → tidak menggandakan revenue_adjustments', function () {
    fakeVR([vr('INV/DUP', 'VOID', 100000, '2026-06-10 10:00:00')]);

    app(RevenueAdjustmentService::class)->process(120, '2026-06-12');
    app(RevenueAdjustmentService::class)->process(120, '2026-06-12');

    expect(RevenueAdjustment::where('transaction_number', 'INV/DUP')->count())->toBe(1);
});

it('tanpa report_run nota → old/new null tapi koreksi tetap tercatat', function () {
    fakeVR([vr('INV/NOOLD', 'VOID', 50000, '2026-06-10 10:00:00')]);

    $s = app(RevenueAdjustmentService::class)->process(120, '2026-06-12');

    expect($s->byDate['2026-06-10']['old'])->toBeNull()
        ->and($s->byDate['2026-06-10']['new'])->toBeNull()
        ->and($s->byDate['2026-06-10']['correction'])->toBe(50000)
        ->and(RevenueAdjustment::where('transaction_number', 'INV/NOOLD')->exists())->toBeTrue();
});
