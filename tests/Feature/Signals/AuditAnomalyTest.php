<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Models\TransactionAuditConfig;
use App\Modules\Signals\DepositExpiryDetector;
use App\Modules\Signals\OffPriceSaleDetector;
use App\Modules\Signals\PaymentAnomalyDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const ADAY = '2026-06-17';

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
        'transaction_audit.review_mode' => true,
        'transaction_audit.cashless_methods' => ['QRIS', 'DEPOSIT', 'TRANSFER'],
        'transaction_audit.proof_required_methods' => ['TRANSFER'],
        'transaction_audit.b2b_customer_groups' => [],
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'payment_anomaly_min_amount' => 50000, 'offprice_tolerance_pct' => 5, 'deposit_expiry_lead_days' => 14]);
});

function auditRows(array $rows): void
{
    Http::fake(['*' => Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $rows])]);
}

// ---------- OPS-1403 PAYMENT_ANOMALY ----------
it('PAYMENT_ANOMALY: QRIS change_amount janggal (nota 9718) → flag, review, low/digest', function () {
    auditRows([['transaction_number' => 'INV/9718', 'id_cashier' => 5, 'grand_total' => 187680,
        'payments' => [['payment_method' => 'QRIS', 'amount' => 274680, 'change_amount' => 87000, 'payment_proof' => null]]]]);

    $r = app(PaymentAnomalyDetector::class)->detect(120, ADAY);
    $s = SignalEvent::where('ref_transaction_number', 'INV/9718')->first();
    expect($r)->toHaveCount(1)->and($s->type)->toBe('PAYMENT_ANOMALY')->and($s->severity)->toBe('low')
        ->and($s->payload_json['review_required'])->toBeTrue()
        ->and($s->payload_json['reasons'])->toContain('cashless_change')
        ->and((float) $s->payload_json['anomaly_amount'])->toEqual(87000.0);
});

it('PAYMENT_ANOMALY: change kecil ≤ ambang → tak flag', function () {
    auditRows([['transaction_number' => 'INV/S', 'id_cashier' => 5, 'grand_total' => 100000,
        'payments' => [['payment_method' => 'QRIS', 'amount' => 130000, 'change_amount' => 30000]]]]); // 30k ≤ 50k

    expect(app(PaymentAnomalyDetector::class)->detect(120, ADAY))->toHaveCount(0);
});

it('PAYMENT_ANOMALY: tunai dengan kembalian normal → tak flag', function () {
    auditRows([['transaction_number' => 'INV/CASH', 'id_cashier' => 5, 'grand_total' => 100000,
        'payments' => [['payment_method' => 'CASH', 'amount' => 200000, 'change_amount' => 100000]]]]);

    expect(app(PaymentAnomalyDetector::class)->detect(120, ADAY))->toHaveCount(0);
});

// ---------- OPS-1404 OFF_PRICE_SALE ----------
it('OFF_PRICE_SALE: harga baris < price-list di luar toleransi (nota 9721) → flag', function () {
    auditRows([['transaction_number' => 'INV/9721', 'id_cashier' => 7, 'grand_total' => 10000,
        'services' => [['price' => 10000, 'quantity' => 1, 'service_data' => ['price' => 14000]]]]]); // gap 28.5% > 5%

    $r = app(OffPriceSaleDetector::class)->detect(120, ADAY);
    expect($r)->toHaveCount(1)
        ->and(SignalEvent::where('type', 'OFF_PRICE_SALE')->first()->payload_json['offending_lines'][0]['gap_pct'])->toEqual(28.57);
});

it('OFF_PRICE_SALE: grup B2B resmi dikecualikan', function () {
    config(['transaction_audit.b2b_customer_groups' => [2]]);
    auditRows([['transaction_number' => 'INV/B2B', 'grand_total' => 10000,
        'customer' => ['id_customer' => 9, 'id_customer_group' => 2],
        'services' => [['price' => 10000, 'service_data' => ['price' => 14000]]]]]);

    expect(app(OffPriceSaleDetector::class)->detect(120, ADAY))->toHaveCount(0);
});

it('OFF_PRICE_SALE: dalam toleransi → tak flag', function () {
    auditRows([['transaction_number' => 'INV/OK', 'grand_total' => 13800,
        'services' => [['price' => 13800, 'service_data' => ['price' => 14000]]]]]); // gap 1.4% < 5%

    expect(app(OffPriceSaleDetector::class)->detect(120, ADAY))->toHaveCount(0);
});

// ---------- OPS-1406 DEPOSIT_EXPIRY ----------
it('DEPOSIT_EXPIRY: deposit kedaluwarsa dalam lead-days → monitor (low, tanpa PII)', function () {
    auditRows([['transaction_number' => 'INV/D', 'grand_total' => 50000,
        'customer' => ['id_customer' => 777, 'name' => 'Budi', 'phone' => '0812', 'deposit_balance' => 34180, 'deposit_active_until' => '2026-06-25', 'deposit_status' => 'ACTIVE']]]); // 8 hari ≤ 14

    $r = app(DepositExpiryDetector::class)->detect(120, ADAY);
    $s = SignalEvent::where('type', 'DEPOSIT_EXPIRY')->first();
    expect($r)->toHaveCount(1)->and($s->severity)->toBe('low')
        ->and($s->payload_json['id_customer'])->toBe(777)
        ->and($s->payload_json['days_until_expiry'])->toBe(8);
    expect(json_encode($s->payload_json))->not->toContain('Budi')->not->toContain('0812');
});

it('DEPOSIT_EXPIRY: masih jauh dari kedaluwarsa → tak flag', function () {
    auditRows([['transaction_number' => 'INV/F', 'customer' => ['id_customer' => 1, 'deposit_active_until' => '2026-09-16']]]); // >14 hari

    expect(app(DepositExpiryDetector::class)->detect(120, ADAY))->toHaveCount(0);
});

it('audit detektor idempoten per nota/customer per hari', function () {
    auditRows([['transaction_number' => 'INV/9718', 'id_cashier' => 5, 'grand_total' => 187680,
        'payments' => [['payment_method' => 'QRIS', 'amount' => 274680, 'change_amount' => 87000]]]]);

    app(PaymentAnomalyDetector::class)->detect(120, ADAY);
    app(PaymentAnomalyDetector::class)->detect(120, ADAY);
    expect(SignalEvent::where('type', 'PAYMENT_ANOMALY')->count())->toBe(1);
});
