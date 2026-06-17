<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Signals\AgingReceivableDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function fakeUnpaid(array $rows): void
{
    Http::fake(['*payment_status=UNPAID*' => Http::response(['data' => $rows, 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null])]);
}

function unpaidRow(string $no, string $created): array
{
    return [
        'transaction_number' => $no, 'status' => 'COMPLETED', 'payment_status' => 'UNPAID',
        'grand_total' => 1500000, 'created_at' => $created, 'id_cashier' => 312,
    ];
}

function scanAging(?int $days = null): \Illuminate\Support\Collection
{
    return app(AgingReceivableDetector::class)->scan(120, '2026-06-20', $days);
}

it('unpaid melewati ambang umur → AGING_PIUTANG (severity low)', function () {
    fakeUnpaid([unpaidRow('INV/OLD', '2026-06-01 10:00:00')]); // 19 hari > 14

    $sigs = scanAging();
    expect($sigs)->toHaveCount(1);
    $s = $sigs->first();
    expect($s->type)->toBe('AGING_PIUTANG')
        ->and($s->severity)->toBe('low')
        ->and($s->payload_json['age_days'])->toBe(19);
});

it('unpaid masih dalam ambang → tidak ada signal', function () {
    fakeUnpaid([unpaidRow('INV/NEW', '2026-06-18 10:00:00')]); // 2 hari < 14
    expect(scanAging())->toHaveCount(0);
});

it('ambang umur configurable', function () {
    fakeUnpaid([unpaidRow('INV/X', '2026-06-15 10:00:00')]); // 5 hari
    expect(scanAging(3))->toHaveCount(1)   // ambang 3 → 5>3 masuk
        ->and(SignalEvent::count())->toBe(1);
});

it('idempoten', function () {
    fakeUnpaid([unpaidRow('INV/OLD', '2026-06-01 10:00:00')]);
    scanAging();
    scanAging();
    expect(SignalEvent::where('type', 'AGING_PIUTANG')->count())->toBe(1);
});
