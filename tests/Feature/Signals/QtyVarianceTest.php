<?php

use App\Models\CashierInputScore;
use App\Models\Outlet;
use App\Models\TransactionAuditConfig;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\QtyVarianceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'qty_variance_pct' => 20]);
});

function varRows(array $rows): void
{
    Http::fake(['*' => Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $rows])]);
}

function varRange(): DateRange
{
    return new DateRange('2026-06-01', '2026-06-30');
}

it('variance > ambang → qty_variance_count per kasir (atribusi pembuat), agregat', function () {
    // nota 9726: quantity 3 vs actual 1 → variance 66% > 20% ; dua nota kasir 5
    varRows([
        ['transaction_number' => 'INV/9726', 'id_cashier' => 5, 'services' => [['quantity' => 3, 'actual_quantity' => 1]]],
        ['transaction_number' => 'INV/9727', 'id_cashier' => 5, 'services' => [['quantity' => 4, 'actual_quantity' => 1]]],
    ]);

    app(QtyVarianceScorer::class)->scan(120, varRange(), '2026-06');

    $score = CashierInputScore::where(['id_outlet' => 120, 'id_cashier' => 5, 'period' => '2026-06'])->first();
    expect($score->qty_variance_count)->toBe(2);
});

it('variance ≤ ambang → tak dihitung', function () {
    varRows([['transaction_number' => 'INV/A', 'id_cashier' => 5, 'services' => [['quantity' => 10, 'actual_quantity' => 9]]]]); // 10% ≤ 20%

    app(QtyVarianceScorer::class)->scan(120, varRange(), '2026-06');
    expect(CashierInputScore::count())->toBe(0);
});

it('actual_quantity null → diabaikan (tak ada data variance)', function () {
    varRows([['transaction_number' => 'INV/B', 'id_cashier' => 5, 'services' => [['quantity' => 3]]]]);

    app(QtyVarianceScorer::class)->scan(120, varRange(), '2026-06');
    expect(CashierInputScore::count())->toBe(0);
});

it('tak menimpa error_count/txn_count dari OPS-603 (menyatu, bukan overwrite)', function () {
    // skor void/refund existing
    $existing = CashierInputScore::create(['id_outlet' => 120, 'id_cashier' => 5, 'period' => '2026-06', 'error_count' => 7, 'txn_count' => 20, 'rate' => 0.35]);
    varRows([['transaction_number' => 'INV/9726', 'id_cashier' => 5, 'services' => [['quantity' => 3, 'actual_quantity' => 1]]]]);

    app(QtyVarianceScorer::class)->scan(120, varRange(), '2026-06');

    $fresh = $existing->fresh();
    expect($fresh->qty_variance_count)->toBe(1)
        ->and($fresh->error_count)->toBe(7)   // utuh
        ->and($fresh->txn_count)->toBe(20);
});

it('command oms:score-qty-variance jalan', function () {
    varRows([['transaction_number' => 'INV/9726', 'id_cashier' => 5, 'services' => [['quantity' => 3, 'actual_quantity' => 1]]]]);

    $this->artisan('oms:score-qty-variance', ['--month' => '2026-06'])->assertSuccessful();
    expect(CashierInputScore::where('id_cashier', 5)->first()->qty_variance_count)->toBe(1);
});
