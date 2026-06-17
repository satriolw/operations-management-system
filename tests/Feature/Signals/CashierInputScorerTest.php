<?php

use App\Models\CashierInputScore;
use App\Models\Outlet;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\CashierInputScorer;
use App\Modules\Signals\ReasonClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Outlet::factory()->create(['id_outlet' => 120]);
});

function fakeVR2(array $void, array $refund = []): void
{
    Http::fake(function (Request $r) use ($void, $refund) {
        parse_str(parse_url($r->url(), PHP_URL_QUERY) ?? '', $q);
        $data = ($q['status'] ?? '') === 'REFUND' ? $refund : (($q['status'] ?? '') === 'VOID' ? $void : []);

        return Http::response(['data' => $data, 'last_page' => 1, 'current_page' => 1, 'next_page_url' => null]);
    });
}

function vrow(string $no, int $cashier, ?int $by, string $reason): array
{
    return [
        'transaction_number' => $no, 'status' => 'VOID', 'grand_total' => 50000,
        'created_at' => '2026-06-10 10:00:00', 'approve_refund_void_date' => '2026-06-10 11:00:00',
        'void_notes' => $reason, 'id_cashier' => $cashier, 'refund_void_by' => $by ?? $cashier,
    ];
}

it('klasifikasi alasan', function () {
    $c = new ReasonClassifier();
    expect($c->classify('salah input nota'))->toBe(ReasonClassifier::INPUT_ERROR)
        ->and($c->classify('customer belum bayar'))->toBe(ReasonClassifier::ABANDONED)
        ->and($c->classify('ingin ganti item'))->toBe(ReasonClassifier::CHANGE_REQUEST)
        ->and($c->classify(''))->toBe(ReasonClassifier::OTHER);
});

it('rate input-error per kasir (2 dari 3 → 0.6667)', function () {
    fakeVR2([
        vrow('INV/1', 205, null, 'salah input nota'),
        vrow('INV/2', 205, null, 'double input'),
        vrow('INV/3', 205, null, 'customer ingin ganti'),
    ]);

    app(CashierInputScorer::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'), '2026-06');

    $s = CashierInputScore::where('id_cashier', 205)->first();
    expect($s->error_count)->toBe(2)->and($s->txn_count)->toBe(3)
        ->and((float) $s->rate)->toBe(0.6667);
});

it('ATRIBUSI ke id_cashier (pembuat nota), BUKAN refund_void_by', function () {
    // requester 200 ≠ cashier 205 → harus diatribusikan ke 205
    fakeVR2([vrow('INV/X', 205, 200, 'salah input')]);

    app(CashierInputScorer::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'), '2026-06');

    expect(CashierInputScore::where('id_cashier', 205)->exists())->toBeTrue()
        ->and(CashierInputScore::where('id_cashier', 200)->exists())->toBeFalse();
});

it('idempoten per outlet+cashier+periode (updateOrCreate)', function () {
    fakeVR2([vrow('INV/1', 205, null, 'salah input')]);
    app(CashierInputScorer::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'), '2026-06');
    app(CashierInputScorer::class)->scan(120, new DateRange('2026-06-05', '2026-06-12'), '2026-06');

    expect(CashierInputScore::where('id_cashier', 205)->count())->toBe(1);
});
