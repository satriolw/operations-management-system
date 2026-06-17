<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\BatchApprovalDetector;
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

function fakeVoidRows(array $void): void
{
    Http::fake(function (Request $r) use ($void) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);

        return Http::response(['data' => ($q['status'] ?? '') === 'VOID' ? $void : [], 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function void180(string $no, string $approveTime): array
{
    return [
        'transaction_number' => $no, 'status' => 'VOID', 'grand_total' => 45000,
        'created_at' => '2026-06-09 10:00:00', 'approve_refund_void_date' => $approveTime,
        'refund_void_by' => 180, 'refund_void_approved_by' => 180, 'id_cashier' => 180,
    ];
}

function scanBatch(): \Illuminate\Support\Collection
{
    return app(BatchApprovalDetector::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'));
}

it('4 void approver sama menit sama → 1 signal batch (kasus user 180 @ 11:58)', function () {
    fakeVoidRows([
        void180('INV/1', '2026-06-09 11:58:01'),
        void180('INV/2', '2026-06-09 11:58:20'),
        void180('INV/3', '2026-06-09 11:58:45'),
        void180('INV/4', '2026-06-09 11:58:59'),
    ]);

    $sigs = scanBatch();
    expect($sigs)->toHaveCount(1);
    $s = $sigs->first();
    expect($s->type)->toBe('BATCH_APPROVAL')
        ->and($s->payload_json['count'])->toBe(4)
        ->and($s->payload_json['approved_by'])->toBe(180);
});

it('≤ ambang (2) dalam satu menit → tidak ada signal', function () {
    fakeVoidRows([void180('INV/1', '2026-06-09 11:58:01'), void180('INV/2', '2026-06-09 11:58:30')]);
    expect(scanBatch())->toHaveCount(0);
});

it('tersebar antar-menit → tidak dianggap batch', function () {
    fakeVoidRows([
        void180('INV/1', '2026-06-09 11:58:01'),
        void180('INV/2', '2026-06-09 11:59:01'),
        void180('INV/3', '2026-06-09 12:00:01'),
    ]);
    expect(scanBatch())->toHaveCount(0);
});

it('ambang configurable', function () {
    config(['signals.batch_threshold' => 1]); // >1 → 2+ memicu
    fakeVoidRows([void180('INV/1', '2026-06-09 11:58:01'), void180('INV/2', '2026-06-09 11:58:30')]);
    expect(scanBatch())->toHaveCount(1);
});

it('idempoten', function () {
    fakeVoidRows([void180('INV/1', '2026-06-09 11:58:01'), void180('INV/2', '2026-06-09 11:58:20'), void180('INV/3', '2026-06-09 11:58:40')]);
    scanBatch();
    scanBatch();
    expect(SignalEvent::where('type', 'BATCH_APPROVAL')->count())->toBe(1);
});
