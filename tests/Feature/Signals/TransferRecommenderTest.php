<?php

use App\Models\Outlet;
use App\Models\OutletCapacity;
use App\Models\SignalEvent;
use App\Modules\Signals\OverloadCheck;
use App\Modules\Signals\TransferRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const RNOW = '2026-06-17 10:00:00';

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
]));

/** Outlet + kapasitas 40 kg/jam (4×10), ambang 80%. */
function rcapOutlet(int $id): Outlet
{
    $o = Outlet::factory()->create(['id_outlet' => $id]);
    OutletCapacity::factory()->create([
        'id_outlet' => $id, 'machines' => 4, 'throughput_kg_per_machine_hour' => 10,
        'kg_per_day' => null, 'capacity_kg_per_hour' => null, 'overload_threshold_pct' => 80,
    ]);

    return $o;
}

/**
 * Fake activeOrders per outlet: $demandByOutlet[id] = kg (deadline 1 jam → demand = kg).
 * Outlet tanpa entri → backlog kosong.
 */
function fakeOrdersByOutlet(array $demandByOutlet): void
{
    Http::fake(function (Request $req) use ($demandByOutlet) {
        parse_str(parse_url($req->url(), PHP_URL_QUERY) ?? '', $q);
        $id = (int) ($q['id_outlet'] ?? 0);
        $kg = $demandByOutlet[$id] ?? 0;
        $data = $kg > 0 ? [[
            'id_transaction' => $id, 'quantity' => $kg, 'progress_percentage' => 0,
            'estimated_completion_date' => '2026-06-17 11:00:00', 'completion_date' => null,
        ]] : [];

        return Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $data]);
    });
}

it('menyarankan hub utilisasi terendah dgn kapasitas sisa, urut naik', function () {
    rcapOutlet(120); // overload: demand 50 → util 125%, excess 10
    rcapOutlet(121); // hub bagus: demand 8 → util 20%, spare 32
    rcapOutlet(122); // hub sibuk: demand 36 → util 90% (≥ ambang) → DIKECUALIKAN
    rcapOutlet(124); // hub ok: demand 20 → util 50%, spare 20
    Outlet::factory()->create(['id_outlet' => 123]); // tanpa kapasitas → DIKECUALIKAN

    fakeOrdersByOutlet([120 => 50, 121 => 8, 122 => 36, 124 => 20]);

    $rec = app(TransferRecommender::class)->recommend(120, RNOW);

    expect($rec['excess_kg_per_hour'])->toEqual(10.0)
        ->and(collect($rec['candidates'])->pluck('id_outlet')->all())->toBe([121, 124]) // urut util naik
        ->and($rec['candidates'][0]['utilization_pct'])->toEqual(20.0)
        ->and($rec['candidates'][0]['can_absorb'])->toBeTrue();   // spare 32 ≥ excess 10
});

it('mengecualikan outlet subjek (yang overload) dari kandidat', function () {
    rcapOutlet(120);
    rcapOutlet(121);
    fakeOrdersByOutlet([120 => 50, 121 => 8]);

    $rec = app(TransferRecommender::class)->recommend(120, RNOW);
    expect(collect($rec['candidates'])->pluck('id_outlet')->all())->not->toContain(120);
});

it('can_absorb false bila kapasitas sisa hub < kelebihan beban subjek', function () {
    rcapOutlet(120); // demand 78 → excess 38
    rcapOutlet(121); // demand 20 → spare 20 (< 38)
    fakeOrdersByOutlet([120 => 78, 121 => 20]);

    $rec = app(TransferRecommender::class)->recommend(120, RNOW);
    expect($rec['excess_kg_per_hour'])->toEqual(38.0)
        ->and($rec['candidates'][0]['can_absorb'])->toBeFalse();
});

it('tanpa hub layak → daftar kandidat kosong (bukan error)', function () {
    rcapOutlet(120);
    rcapOutlet(121); // demand 40 → util 100% (sibuk) → bukan kandidat
    fakeOrdersByOutlet([120 => 50, 121 => 40]);

    $rec = app(TransferRecommender::class)->recommend(120, RNOW);
    expect($rec['candidates'])->toBe([]);
});

it('TIDAK auto-transfer: hanya request GET (baca), tak ada tulis ke NEVIRA', function () {
    rcapOutlet(120);
    rcapOutlet(121);
    fakeOrdersByOutlet([120 => 50, 121 => 8]);

    app(TransferRecommender::class)->recommend(120, RNOW);

    Http::assertSent(fn (Request $r) => $r->method() === 'GET');
    expect(collect(Http::recorded())->every(fn ($pair) => $pair[0]->method() === 'GET'))->toBeTrue();
});

it('overload memuat rekomendasi transfer di payload sinyal (OPS-1103 ↔ 1104)', function () {
    rcapOutlet(120);
    rcapOutlet(121);
    fakeOrdersByOutlet([120 => 50, 121 => 8]);

    $signal = app(OverloadCheck::class)->check(120, RNOW);

    expect($signal->severity)->toBe('high')
        ->and($signal->payload_json)->toHaveKey('transfer_recommendation')
        ->and($signal->payload_json['transfer_recommendation']['candidates'][0]['id_outlet'])->toBe(121);
});

it('warning (bukan overload) TANPA rekomendasi transfer di payload', function () {
    rcapOutlet(120); // demand 36 → util 90% → warning
    fakeOrdersByOutlet([120 => 36]);

    $signal = app(OverloadCheck::class)->check(120, RNOW);
    expect($signal->severity)->toBe('low')
        ->and($signal->payload_json)->not->toHaveKey('transfer_recommendation');
    expect(SignalEvent::count())->toBe(1);
});
