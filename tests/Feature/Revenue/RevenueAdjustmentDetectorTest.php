<?php

use App\Modules\Revenue\RevenueAdjustmentDetector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
]));

/** Fake voidRefunds: routing status=VOID / status=REFUND → halaman tunggal. */
function fakeVoidRefund(array $void, array $refund): void
{
    Http::fake(function (Request $r) use ($void, $refund) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
        $data = ($q['status'] ?? '') === 'REFUND' ? $refund : (($q['status'] ?? '') === 'VOID' ? $void : []);

        return Http::response(['data' => $data, 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function txn(array $o): array
{
    return array_merge([
        'transaction_number' => 'INV/X', 'status' => 'VOID', 'grand_total' => 100000,
        'created_at' => '2026-06-11 10:00:00', 'approve_refund_void_date' => '2026-06-12 10:00:00',
        'void_notes' => 'salah input', 'id_cashier' => 181, 'payment_status' => 'UNPAID',
    ], $o);
}

it('cross-day: void disetujui hari ini atas nota lampau → masuk', function () {
    fakeVoidRefund([txn(['transaction_number' => 'INV/1', 'created_at' => '2026-06-11 13:00:00'])], []);

    $c = app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12');

    expect($c)->toHaveCount(1)
        ->and($c[0]->transactionNumber)->toBe('INV/1')
        ->and($c[0]->notaDate)->toBe('2026-06-11')
        ->and($c[0]->approvedDate)->toBe('2026-06-12')
        ->and($c[0]->type)->toBe('VOID');
});

it('same-day: disetujui hari ini atas nota hari ini → TIDAK masuk', function () {
    fakeVoidRefund([txn(['transaction_number' => 'INV/2', 'created_at' => '2026-06-12 09:00:00'])], []);

    expect(app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12'))->toHaveCount(0);
});

it('mencakup VOID (unpaid) DAN REFUND (paid)', function () {
    fakeVoidRefund(
        [txn(['transaction_number' => 'INV/V', 'status' => 'VOID', 'payment_status' => 'UNPAID', 'created_at' => '2026-06-10 10:00:00'])],
        [txn(['transaction_number' => 'INV/R', 'status' => 'REFUND', 'payment_status' => 'PAID', 'created_at' => '2026-06-09 10:00:00', 'refund_notes' => 'komplain', 'void_notes' => null])],
    );

    $c = app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12');
    expect($c->pluck('type')->sort()->values()->all())->toBe(['REFUND', 'VOID']);
});

it('batch-approval: beberapa cross-day disetujui hari sama → semua masuk', function () {
    fakeVoidRefund([
        txn(['transaction_number' => 'INV/B1', 'created_at' => '2026-06-09 10:00:00']),
        txn(['transaction_number' => 'INV/B2', 'created_at' => '2026-06-10 11:00:00']),
        txn(['transaction_number' => 'INV/B3', 'created_at' => '2026-06-11 12:00:00']),
    ], []);

    expect(app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12'))->toHaveCount(3);
});

it('R1 batas tengah malam: nota 23:30 WIB tetap tgl 06-11 (tidak tergeser)', function () {
    fakeVoidRefund([txn([
        'transaction_number' => 'INV/MID', 'created_at' => '2026-06-11 23:30:00',
        'approve_refund_void_date' => '2026-06-12 00:10:00',
        'services' => [['created_at' => '2026-06-11T16:30:00.000000Z']], // UTC nested — harus diabaikan
    ])], []);

    $c = app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12');
    expect($c)->toHaveCount(1)->and($c[0]->notaDate)->toBe('2026-06-11');
});

it('tidak menghitung ganda (transaction_number unik) & idempoten', function () {
    fakeVoidRefund([
        txn(['transaction_number' => 'INV/DUP', 'created_at' => '2026-06-10 10:00:00']),
        txn(['transaction_number' => 'INV/DUP', 'created_at' => '2026-06-10 10:00:00']),
    ], []);

    $a = app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12');
    $b = app(RevenueAdjustmentDetector::class)->detect(120, '2026-06-12');
    expect($a)->toHaveCount(1)->and($a->pluck('transactionNumber'))->toEqual($b->pluck('transactionNumber'));
});
