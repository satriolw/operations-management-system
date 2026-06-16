<?php

use App\Modules\Reporting\DailyDashboardAggregator;
use App\Modules\Reporting\DTO\DailyMetrics;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id',
    'nevira.token' => 'tok-static',
    'nevira.service_username' => null, // paksa ConfigTokenProvider (tak bergantung .env)
    'nevira.service_password' => null,
]));

function fakeDashboard(array $payload): void
{
    Http::fake(['*reports/dashboard*' => Http::response($payload)]);
}

it('agregasi dashboard: angka cocok dgn response NEVIRA', function () {
    fakeDashboard(json_decode(file_get_contents(base_path('tests/Fixtures/nevira/dashboard.json')), true));

    $m = app(DailyDashboardAggregator::class)->forOutlet(120, '2026-06-12');

    expect($m)->toBeInstanceOf(DailyMetrics::class)
        ->and($m->totalSales)->toBe(12500000)
        ->and($m->avgTransaction)->toBe(156250)
        ->and($m->avgCustomerSpending)->toBe(178571)
        ->and($m->txnCount)->toBe(80)
        ->and($m->volumes)->toBe(['m2' => 0, 'pasang' => 0, 'lembar' => 0]);
});

it('txn_count diturunkan dari total ÷ avg bila tak tersedia', function () {
    fakeDashboard(['total_sales' => 12500000, 'avg_transaction' => 156250]); // tanpa txn_count

    expect(app(DailyDashboardAggregator::class)->forOutlet(120, '2026-06-12')->txnCount)->toBe(80);
});

it('txn_count diturunkan dari order_type_summary bila tak ada txn_count & avg', function () {
    fakeDashboard(['total_sales' => 0, 'avg_transaction' => 0, 'order_type_summary' => ['dine_in' => 50, 'take_away' => 30]]);

    expect(app(DailyDashboardAggregator::class)->forOutlet(120, '2026-06-12')->txnCount)->toBe(80);
});

it('toTokens menyediakan token renderer', function () {
    fakeDashboard(['total_sales' => 1000, 'avg_transaction' => 100, 'txn_count' => 10, 'unit_volumes' => ['kg' => 67, 'pcs' => 121]]);

    $tokens = app(DailyDashboardAggregator::class)->forOutlet(120, '2026-06-12')->toTokens();
    expect($tokens)->toMatchArray(['total_sales' => 1000, 'txn_count' => 10, 'volume_kg' => 67, 'volume_pcs' => 121]);
});
