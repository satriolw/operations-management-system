<?php

use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DashboardDTO;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\Exceptions\NeviraAuthException;
use App\Modules\Ingestion\NeviraApiSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * OPS-102 · contract test NeviraApiSource memakai fixture response NYATA (mock HTTP).
 * Menegaskan: binding interface (anti-corruption), paginasi otomatis, 429 retry,
 * 401 bukan-transient, bearer token dari config (bukan hardcode).
 */

function neviraFixture(string $name): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/nevira/{$name}.json")), true);
}

/** Router fake berdasar query (tahan urutan param). */
function fakeNevira(): void
{
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);

        if (str_contains($path, 'reports/dashboard')) {
            return Http::response(neviraFixture('dashboard'));
        }
        if (($q['payment_status'] ?? null) === 'UNPAID') {
            return Http::response(neviraFixture('transactions_unpaid'));
        }
        if (($q['status'] ?? null) === 'REFUND') {
            return Http::response(neviraFixture('transactions_refund'));
        }
        if (($q['status'] ?? null) === 'VOID') {
            return Http::response(neviraFixture(
                ($q['page'] ?? '1') == '2' ? 'transactions_void_page2' : 'transactions_void_page1'
            ));
        }

        return Http::response([], 200);
    });
}

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id',
        'nevira.token' => 'secret-token-xyz',
        'nevira.per_page' => 50,
        'nevira.retry.times' => 3,
    ]);
    Sleep::fake(); // backoff tak benar-benar tidur
});

it('domain menerima interface TransactionSource (anti-corruption), di-resolve ke NeviraApiSource', function () {
    $source = app(TransactionSource::class);

    expect($source)->toBeInstanceOf(TransactionSource::class)
        ->and($source)->toBeInstanceOf(NeviraApiSource::class);
});

it('voidRefunds mengumpulkan SEMUA halaman VOID + REFUND', function () {
    fakeNevira();

    $rows = app(TransactionSource::class)
        ->voidRefunds(120, new DateRange('2026-06-05', '2026-06-12'));

    // VOID page1 (2) + VOID page2 (1) + REFUND (1) = 4
    expect($rows)->toHaveCount(4);
    expect($rows->pluck('id_transaction')->all())
        ->toContain(8134, 7971, 6003, 8200);

    // Bukti paginasi: halaman ke-2 VOID benar-benar diminta.
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'status=VOID') && str_contains($r->url(), 'page=2'));
});

it('unpaid memetakan filter payment_status=UNPAID & mengumpulkan data', function () {
    fakeNevira();

    $rows = app(TransactionSource::class)
        ->unpaid(117, new DateRange('2026-06-12', '2026-06-12'));

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn ($r) => $r['payment_status'] === 'UNPAID'))->toBeTrue();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'payment_status=UNPAID'));
});

it('dailyDashboard mengembalikan DashboardDTO dgn angka sesuai response', function () {
    fakeNevira();

    $dto = app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect($dto)->toBeInstanceOf(DashboardDTO::class)
        ->and($dto->outletId)->toBe(120)
        ->and($dto->date)->toBe('2026-06-12')
        ->and($dto->get('total_sales'))->toBe(12500000)
        ->and($dto->get('txn_count'))->toBe(80);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'reports/dashboard')
        && str_contains($r->url(), 'id_outlet=120'));
});

it('mengirim Bearer token DARI CONFIG (tidak hardcode)', function () {
    fakeNevira();

    app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer secret-token-xyz'));
});

it('429 dihormati: backoff lalu retry sampai sukses', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('rate limited', 429)
            ->push(neviraFixture('dashboard'), 200),
    ]);

    $dto = app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect($dto->get('total_sales'))->toBe(12500000);
    Http::assertSentCount(2);     // 1x 429 + 1x sukses
    Sleep::assertSlept(fn () => true); // backoff dipanggil
});

it('5xx transient juga di-retry', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push('boom', 503)
            ->push(neviraFixture('dashboard'), 200),
    ]);

    app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    Http::assertSentCount(2);
});

it('401 BUKAN transient: lempar NeviraAuthException, TIDAK di-retry', function () {
    Http::fake(['*' => Http::response('unauthorized', 401)]);

    expect(fn () => app(TransactionSource::class)->dailyDashboard(120, '2026-06-12'))
        ->toThrow(NeviraAuthException::class);

    Http::assertSentCount(1); // tidak ada retry backoff untuk 401
});

it('403 juga dilempar sebagai NeviraAuthException tanpa retry', function () {
    Http::fake(['*' => Http::response('forbidden', 403)]);

    expect(fn () => app(TransactionSource::class)->dailyDashboard(120, '2026-06-12'))
        ->toThrow(NeviraAuthException::class);

    Http::assertSentCount(1);
});

it('fixture nyata mengandung jebakan timezone (txn-level WIB vs services UTC)', function () {
    $void = neviraFixture('transactions_void_page1')['data'][0];

    // R1: timestamp tingkat-transaksi WIB, nested services UTC — selisih 7 jam.
    expect($void['created_at'])->toBe('2026-06-11 13:10:12')
        ->and($void['services'][0]['created_at'])->toBe('2026-06-11T06:10:12.000000Z');
    // Normalisasi eksplisit ditangani OPS-103.
});

it('DateRange.lookback membentuk jendela 7 hari untuk Penyesuaian Revenue', function () {
    $range = DateRange::lookback('2026-06-12', 7);

    expect($range->startDate())->toBe('2026-06-05')
        ->and($range->endDate())->toBe('2026-06-12');
});
