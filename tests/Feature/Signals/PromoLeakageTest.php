<?php

use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Models\TransactionAuditConfig;
use App\Modules\Ingestion\DTO\AuditTransaction;
use App\Modules\Signals\PromoLeakageDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const AUDIT_DAY = '2026-06-17';

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
    'transaction_audit.review_mode' => true, 'transaction_audit.promo_whitelist' => [],
]));

function fakeAuditFixture(): void
{
    Http::fake(['*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/nevira/audit_transactions.json')), true))]);
}

function fakeAuditRows(array $rows): void
{
    Http::fake(['*' => Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $rows])]);
}

// ---------- OPS-1401 DTO ----------
it('AuditTransaction DTO: parse promos/payments/services/deposit; minim-PII', function () {
    $row = json_decode(file_get_contents(base_path('tests/Fixtures/nevira/audit_transactions.json')), true)['data'][0];
    $t = AuditTransaction::fromRow($row);

    expect($t->promoTotal())->toEqual(36978.0)               // 31028 + 5950
        ->and($t->payments()[0]['change_amount'])->toEqual(87000.0)
        ->and($t->services()[0]['list_price'])->toEqual(14000.0)
        ->and($t->services()[0]['actual_quantity'])->toEqual(1.0)
        ->and($t->deposit()['balance'])->toEqual(34180.0)
        ->and($t->idCustomer())->toBe(5512);
    // DTO tak mengekspos nama/telepon (minim-PII)
    expect(method_exists($t, 'name'))->toBeFalse();
});

// ---------- OPS-1402 detector ----------
it('PROMO_LEAKAGE: diskon > cap harian → flag (perlu ditinjau, low/digest)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 500000, 'promo_leak_pct' => 99]);
    fakeAuditFixture(); // total diskon 36978 + 500000 = 536978 > cap 500000

    $s = app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY);
    expect($s)->not->toBeNull()
        ->and($s->type)->toBe('PROMO_LEAKAGE')->and($s->severity)->toBe('low')
        ->and($s->payload_json['review_required'])->toBeTrue()
        ->and((float) $s->payload_json['total_discount'])->toEqual(536978.0)
        ->and($s->payload_json['by_cashier'])->toHaveKey('181');
});

it('payload PROMO_LEAKAGE TANPA PII pelanggan', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 100000]);
    fakeAuditFixture();

    app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY);
    $json = json_encode(SignalEvent::where('type', 'PROMO_LEAKAGE')->first()->payload_json);
    expect($json)->not->toContain('Budi')->not->toContain('Siti')->not->toContain('08123456789');
});

it('di bawah ambang (cap & pct) → tak ada sinyal', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 999999999, 'promo_leak_pct' => 200]);
    fakeAuditFixture(); // diskon 536978 / omzet 350000 = 153% < 200 & < cap → tak flag

    expect(app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY))->toBeNull();
});

it('promo whitelist dikecualikan dari agregasi', function () {
    config(['transaction_audit.promo_whitelist' => ['PROMO BESAR', 'Gratis Biaya Antar Jemput', 'DISKON 5% MEMBERSHIP']]);
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 1, 'promo_leak_pct' => 0.01]);
    fakeAuditFixture(); // semua promo di-whitelist → total 0 → null

    expect(app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY))->toBeNull();
});

it('flag via % omzet (bukan cap)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 999999999, 'promo_leak_pct' => 10]);
    fakeAuditRows([
        ['transaction_number' => 'INV/A', 'id_cashier' => 9, 'grand_total' => 100000, 'promos' => [['name' => 'X', 'amount' => 50000]]],
    ]); // diskon 50000 / omzet 100000 = 50% > 10%

    $s = app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY);
    expect($s)->not->toBeNull()->and((float) $s->payload_json['discount_pct'])->toEqual(50.0);
});

it('idempoten per outlet+hari', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    TransactionAuditConfig::factory()->create(['id_outlet' => 120, 'promo_leak_daily_cap' => 100000]);
    fakeAuditFixture();

    app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY);
    app(PromoLeakageDetector::class)->detect(120, AUDIT_DAY);
    expect(SignalEvent::where('type', 'PROMO_LEAKAGE')->count())->toBe(1);
});
