<?php

use App\Modules\Reporting\RevenueSplitter;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id',
    'nevira.token' => 'tok-static',
    'nevira.service_username' => null,
    'nevira.service_password' => null,
]));

it('piutang = Σ grand_total unpaid; terealisasi = total − piutang; jumlah = total_sales', function () {
    // fixture unpaid nyata (outlet 117): 1.500.000 + 750.000 = 2.250.000
    Http::fake(['*payment_status=UNPAID*' => Http::response(
        json_decode(file_get_contents(base_path('tests/Fixtures/nevira/transactions_unpaid.json')), true)
    )]);

    $split = app(RevenueSplitter::class)->forOutlet(117, '2026-06-12', totalSales: 10000000);

    expect($split->receivable)->toBe(2250000)
        ->and($split->realized)->toBe(7750000)
        ->and($split->balances())->toBeTrue()                       // realized + piutang == total
        ->and($split->realized + $split->receivable)->toBe(10000000);
});

it('tanpa unpaid → piutang Rp0, terealisasi = total', function () {
    Http::fake(['*payment_status=UNPAID*' => Http::response(['data' => [], 'last_page' => 1, 'current_page' => 1])]);

    $split = app(RevenueSplitter::class)->forOutlet(120, '2026-06-12', totalSales: 5000000);

    expect($split->receivable)->toBe(0)
        ->and($split->realized)->toBe(5000000)
        ->and($split->balances())->toBeTrue();
});

it('toTokens → realized & piutang', function () {
    Http::fake(['*payment_status=UNPAID*' => Http::response(['data' => [], 'last_page' => 1, 'current_page' => 1])]);
    $tokens = app(RevenueSplitter::class)->forOutlet(120, '2026-06-12', 5000000)->toTokens();
    expect($tokens)->toBe(['realized' => 5000000, 'piutang' => 0]);
});
