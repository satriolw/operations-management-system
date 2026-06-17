<?php

use App\Models\NeviraCostByOutlet;
use App\Models\Outlet;
use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Signals\CostAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null, 'nevira.merchant_id' => 69,
    'balance.unit_cost' => ['nota_transaksi' => 100, 'cetak_struk' => 50, 'kirim_whatsapp' => 75, 'export_laporan' => null],
    'balance.anomaly_factor' => 3.0, 'balance.anomaly_min_outlets' => 3,
]));

/** Fake history merchant_balance (satu halaman) berisi baris ber-id_outlet. */
function fakeHistory(array $rows): void
{
    Http::fake(['*' => Http::response([
        'saldo_total' => 1000000, 'breakdown' => [],
        'current_page' => 1, 'last_page' => 1, 'next_page_url' => null,
        'history' => $rows,
    ])]);
}

function hrow(int $idOutlet, string $action): array
{
    return ['id_outlet' => $idOutlet, 'action' => $action, 'amount' => -100];
}

it('menghitung biaya per outlet dari history (count × unit_cost) & query-able', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    fakeHistory([
        hrow(120, 'nota_transaksi'), hrow(120, 'nota_transaksi'), // 2×100
        hrow(120, 'cetak_struk'),                                 // 1×50
        hrow(120, 'kirim_whatsapp'),                              // 1×75
    ]);

    $rows = app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));

    $row = NeviraCostByOutlet::where('id_outlet', 120)->first();
    expect($row)->not->toBeNull()
        ->and($row->total_cost)->toBe(325)            // 200+50+75
        ->and($row->counts_json['nota_transaksi'])->toBe(2)
        ->and($row->period)->toBe('2026-06-17_2026-06-17');
});

it('export_laporan (unit cost null) tak menambah biaya', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    fakeHistory([hrow(120, 'nota_transaksi'), hrow(120, 'export_laporan')]);

    app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));
    expect(NeviraCostByOutlet::where('id_outlet', 120)->first()->total_cost)->toBe(100); // export 0
});

it('idempoten per (outlet, period): updateOrCreate, tak menggandakan', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    fakeHistory([hrow(120, 'nota_transaksi')]);

    app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));
    app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));

    expect(NeviraCostByOutlet::where('id_outlet', 120)->count())->toBe(1);
});

it('flag outlet burn abnormal (perlu ditinjau) + sinyal COST_ANOMALY low', function () {
    foreach ([120, 121, 122] as $id) {
        Outlet::factory()->create(['id_outlet' => $id]);
    }
    // 120 spam WhatsApp jauh di atas yang lain (outlier > 3× median)
    $rows = [];
    for ($i = 0; $i < 50; $i++) {
        $rows[] = hrow(120, 'kirim_whatsapp'); // 50×75 = 3750
    }
    $rows[] = hrow(121, 'nota_transaksi'); // 100
    $rows[] = hrow(122, 'nota_transaksi'); // 100  (median = 100)
    fakeHistory($rows);

    app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));

    expect(NeviraCostByOutlet::where('id_outlet', 120)->first()->flagged)->toBeTrue()
        ->and(NeviraCostByOutlet::where('id_outlet', 121)->first()->flagged)->toBeFalse();

    $sig = SignalEvent::where('type', 'COST_ANOMALY')->where('id_outlet', 120)->first();
    expect($sig)->not->toBeNull()->and($sig->severity)->toBe('low');
});

it('populasi kurang (< min_outlets) → tak ada flag (tak ada basis perbandingan)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    $rows = array_fill(0, 50, hrow(120, 'kirim_whatsapp'));
    fakeHistory($rows);

    app(CostAttributionService::class)->attribute(new DateRange('2026-06-17', '2026-06-17'));
    expect(NeviraCostByOutlet::where('id_outlet', 120)->first()->flagged)->toBeFalse();
});

it('command oms:attribute-balance-cost jalan', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    fakeHistory([hrow(120, 'nota_transaksi')]);

    $this->artisan('oms:attribute-balance-cost', ['--start' => '2026-06-17', '--end' => '2026-06-17'])->assertSuccessful();
    expect(NeviraCostByOutlet::count())->toBe(1);
});
