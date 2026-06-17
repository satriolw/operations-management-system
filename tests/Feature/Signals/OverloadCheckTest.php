<?php

use App\Models\Outlet;
use App\Models\OutletCapacity;
use App\Models\SignalEvent;
use App\Modules\Signals\OverloadCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const NOW = '2026-06-17 10:00:00';

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
]));

/** Outlet + kapasitas 40 kg/jam (4 mesin × 10), ambang overload 80%. */
function capOutlet(int $id = 120, int $threshold = 80): Outlet
{
    $o = Outlet::factory()->create(['id_outlet' => $id]);
    OutletCapacity::factory()->create([
        'id_outlet' => $id, 'machines' => 4, 'throughput_kg_per_machine_hour' => 10,
        'kg_per_day' => null, 'capacity_kg_per_hour' => null, 'overload_threshold_pct' => $threshold,
    ]);

    return $o;
}

/** Fake activeOrders → satu halaman (orders aktif: completion_date null). */
function fakeOrders(array $orders): void
{
    Http::fake(['*' => Http::response([
        'current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $orders,
    ])]);
}

function order(float $qty, float $progress, ?string $eta): array
{
    return [
        'id_transaction' => random_int(1, 9999), 'quantity' => $qty,
        'progress_percentage' => $progress, 'estimated_completion_date' => $eta,
        'completion_date' => null, 'status' => 'IN_PROGRESS',
    ];
}

it('kapasitas belum dikonfigurasi → tak ada sinyal', function () {
    Outlet::factory()->create(['id_outlet' => 120]); // tanpa OutletCapacity
    fakeOrders([order(100, 0, '2026-06-17 11:00:00')]);

    expect(app(OverloadCheck::class)->check(120, NOW))->toBeNull();
    expect(SignalEvent::count())->toBe(0);
});

it('di bawah ambang → tak ada sinyal', function () {
    capOutlet();
    // remaining 10kg / 5 jam = 2 kg/jam ÷ 40 = 5% utilisasi
    fakeOrders([order(10, 0, '2026-06-17 15:00:00')]);

    expect(app(OverloadCheck::class)->check(120, NOW))->toBeNull();
});

it('warning (≥ ambang, <100%) → severity LOW (digest)', function () {
    capOutlet();
    // remaining 36kg / 1 jam = 36 ÷ 40 = 90% → warning
    fakeOrders([order(36, 0, '2026-06-17 11:00:00')]);

    $s = app(OverloadCheck::class)->check(120, NOW);
    expect($s)->not->toBeNull()
        ->and($s->type)->toBe('OVERLOAD')
        ->and($s->severity)->toBe('low')
        ->and($s->payload_json['tier'])->toBe('warning')
        ->and((float) $s->payload_json['utilization_pct'])->toEqual(90.0);
});

it('overload (≥100%) → severity HIGH (real-time)', function () {
    capOutlet();
    // remaining 50kg / 1 jam = 50 ÷ 40 = 125% → overload
    fakeOrders([order(50, 0, '2026-06-17 11:00:00')]);

    $s = app(OverloadCheck::class)->check(120, NOW);
    expect($s->severity)->toBe('high')
        ->and($s->payload_json['tier'])->toBe('overload');
});

it('progress mengurangi sisa kg', function () {
    capOutlet();
    // qty 100, progress 80% → sisa 20kg / 1 jam = 20 ÷ 40 = 50% → di bawah ambang
    fakeOrders([order(100, 80, '2026-06-17 11:00:00')]);

    expect(app(OverloadCheck::class)->check(120, NOW))->toBeNull();
});

it('order express berkontribusi besar: deadline dekat → overload', function () {
    capOutlet();
    // sisa 20kg, deadline 0.5 jam → 40 kg/jam ÷ 40 = 100% → overload
    fakeOrders([order(20, 0, '2026-06-17 10:30:00')]);

    expect(app(OverloadCheck::class)->check(120, NOW)->severity)->toBe('high');
});

it('sisa kg sama tapi deadline jauh → di bawah ambang (bukti timbang-deadline)', function () {
    capOutlet();
    // sisa 20kg, deadline 10 jam → 2 kg/jam ÷ 40 = 5% → tak ada sinyal
    fakeOrders([order(20, 0, '2026-06-17 20:00:00')]);

    expect(app(OverloadCheck::class)->check(120, NOW))->toBeNull();
});

it('deadline lewat (overdue) di-floor: kontribusi besar, tanpa /0 atau negatif', function () {
    capOutlet();
    fakeOrders([order(30, 0, '2026-06-17 08:00:00')]); // 2 jam lalu → floor 0.5 jam → 60 ÷ 40 = 150%

    $s = app(OverloadCheck::class)->check(120, NOW);
    expect($s->severity)->toBe('high')
        ->and($s->payload_json['utilization_pct'])->toBeGreaterThan(100);
});

it('idempoten per outlet+jam (re-run tak menggandakan)', function () {
    capOutlet();
    fakeOrders([order(50, 0, '2026-06-17 11:00:00')]);

    app(OverloadCheck::class)->check(120, NOW);
    app(OverloadCheck::class)->check(120, '2026-06-17 10:45:00'); // jam sama

    expect(SignalEvent::where('type', 'OVERLOAD')->count())->toBe(1);
});

it('tenggat tak diketahui (null) → diperlakukan mendesak', function () {
    capOutlet();
    fakeOrders([order(30, 0, null)]); // floor 0.5 jam → 60 ÷ 40 = 150%

    expect(app(OverloadCheck::class)->check(120, NOW)->severity)->toBe('high');
});
