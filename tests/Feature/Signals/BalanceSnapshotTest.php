<?php

use App\Models\NeviraBalanceSnapshot;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\MerchantBalanceDTO;
use App\Modules\Signals\BalanceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
    'nevira.merchant_id' => 69,
]));

function fakeMerchantBalance(): void
{
    Http::fake(fn () => Http::response(
        json_decode(file_get_contents(base_path('tests/Fixtures/nevira/merchant_balance.json')), true)
    ));
}

it('merchantBalance: saldo_total + breakdown dari response', function () {
    fakeMerchantBalance();

    $dto = app(TransactionSource::class)->merchantBalance(new DateRange('2026-06-17', '2026-06-17'));

    expect($dto)->toBeInstanceOf(MerchantBalanceDTO::class)
        ->and($dto->saldoTotal)->toBe(4850000)
        ->and($dto->breakdown['nota_transaksi'])->toBe(1200)
        ->and($dto->breakdown['kirim_whatsapp'])->toBe(300);
});

it('TIDAK menarik history (satu request walau last_page 1989)', function () {
    fakeMerchantBalance();

    app(TransactionSource::class)->merchantBalance(new DateRange('2026-06-17', '2026-06-17'));

    Http::assertSentCount(1); // bukti: tak ikuti next_page_url history
});

it('mengirim merchant_id dari config ke endpoint', function () {
    fakeMerchantBalance();

    app(TransactionSource::class)->merchantBalance(new DateRange('2026-06-17', '2026-06-17'));

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/merchant_balance')
        && str_contains($r->url(), 'merchant_id=69'));
});

it('capture(): snapshot saldo + breakdown ter-persist (ber-stempel waktu)', function () {
    fakeMerchantBalance();

    $snap = app(BalanceSnapshotService::class)->capture('2026-06-17');

    expect($snap->saldo_total)->toBe(4850000)
        ->and($snap->breakdown_json['cetak_struk'])->toBe(1200)
        ->and($snap->captured_at)->not->toBeNull();
    expect(NeviraBalanceSnapshot::count())->toBe(1);
});

it('command oms:capture-balance-snapshot menyimpan snapshot', function () {
    fakeMerchantBalance();

    $this->artisan('oms:capture-balance-snapshot', ['--date' => '2026-06-17'])->assertSuccessful();

    expect(NeviraBalanceSnapshot::count())->toBe(1)
        ->and(NeviraBalanceSnapshot::first()->saldo_total)->toBe(4850000);
});
