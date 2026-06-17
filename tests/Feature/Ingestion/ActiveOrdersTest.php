<?php

use App\Modules\Ingestion\Contracts\TransactionSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * OPS-1102 · contract test activeOrders (backlog order berjalan). Fixture paginasi NEVIRA.
 * Menegaskan: kumpul semua halaman, guard sisi-klien (selesai disaring), tahan null/kosong,
 * id_outlet terkirim. Param server "belum selesai" belum dikonfirmasi → guard completion_date.
 */

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id',
        'nevira.token' => 'secret-token-xyz',
        'nevira.service_username' => null,
        'nevira.service_password' => null,
        'nevira.per_page' => 50,
        'nevira.active_orders_params' => [],
    ]);
    Sleep::fake();
});

function fakeActiveOrders(): void
{
    Http::fake(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);
        $page = (string) ($q['page'] ?? '1');
        $name = $page === '2' ? 'active_orders_page2' : 'active_orders_page1';

        return Http::response(json_decode(file_get_contents(base_path("tests/Fixtures/nevira/{$name}.json")), true));
    });
}

it('mengumpulkan SEMUA halaman backlog & menyaring order selesai (guard sisi-klien)', function () {
    fakeActiveOrders();

    $rows = app(TransactionSource::class)->activeOrders(120);

    // page1: 2 aktif + 1 DONE(completion_date≠null) ; page2: 1 aktif → 3 aktif setelah filter
    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('id_transaction')->all())->toContain(9001, 9002, 9004)
        ->and($rows->pluck('id_transaction')->all())->not->toContain(9003); // selesai disaring

    // bukti paginasi: halaman 2 diminta
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'page=2'));
});

it('membawa field SLA (estimated_completion_date, progress, order_type) utk OPS-1103/1301', function () {
    fakeActiveOrders();

    $first = app(TransactionSource::class)->activeOrders(120)->firstWhere('id_transaction', 9001);

    expect($first)->toHaveKeys(['estimated_completion_date', 'progress_percentage', 'completion_date', 'status', 'updated_at', 'id_rack', 'order_type'])
        ->and($first['completion_date'])->toBeNull()
        ->and($first['order_type'])->toBe('REGULAR');
});

it('mengirim id_outlet ke endpoint transactions', function () {
    fakeActiveOrders();

    app(TransactionSource::class)->activeOrders(117);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/transactions')
        && str_contains($r->url(), 'id_outlet=117'));
});

it('tahan halaman kosong (data [] → koleksi kosong, bukan error)', function () {
    Http::fake(['*' => Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => []])]);

    expect(app(TransactionSource::class)->activeOrders(120))->toHaveCount(0);
});

it('memperlakukan row tanpa key completion_date sebagai aktif (tahan null)', function () {
    Http::fake(['*' => Http::response([
        'current_page' => 1, 'last_page' => 1, 'next_page_url' => null,
        'data' => [
            ['id_transaction' => 1, 'status' => 'IN_PROGRESS'],            // completion_date absen → aktif
            ['id_transaction' => 2, 'completion_date' => '2026-06-16 10:00:00'], // selesai → disaring
        ],
    ])]);

    $rows = app(TransactionSource::class)->activeOrders(120);
    expect($rows)->toHaveCount(1)->and($rows->first()['id_transaction'])->toBe(1);
});
