<?php

use App\Models\Outlet;
use App\Models\OutletOperatingHour;
use App\Models\OutletSlaConfig;
use App\Models\SignalEvent;
use App\Modules\Ingestion\DTO\ActiveOrder;
use App\Modules\Signals\BusinessHoursClock;
use App\Modules\Signals\LateOrderDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const LNOW = '2026-06-17 12:00:00';

beforeEach(fn () => config([
    'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
    'nevira.service_username' => null, 'nevira.service_password' => null,
]));

function openHours(int $id, string $open = '09:00', string $close = '21:00'): void
{
    foreach (range(0, 6) as $wd) {
        OutletOperatingHour::create(['id_outlet' => $id, 'weekday' => $wd, 'is_closed' => false, 'open_time' => $open, 'close_time' => $close]);
    }
}

function fakeLateOrders(array $orders): void
{
    Http::fake(['*' => Http::response(['current_page' => 1, 'last_page' => 1, 'next_page_url' => null, 'data' => $orders])]);
}

function lateOrder(array $o): array
{
    return array_merge(['transaction_number' => 'INV/'.random_int(1, 9999), 'status' => 'WASHING',
        'completion_date' => null, 'progress_percentage' => 10, 'updated_at' => '2026-06-17 11:50:00',
        'id_rack' => 'A-1', 'order_type' => 'REGULAR', 'id_cashier' => 181], $o);
}

// ---------- OPS-1301 DTO ----------
it('ActiveOrder DTO: field SLA WIB, null-safe', function () {
    $dto = ActiveOrder::fromRow(['transaction_number' => 'INV/9', 'status' => 'WASHING',
        'estimated_completion_date' => '2026-06-17 18:00:00', 'completion_date' => null,
        'updated_at' => '2026-06-17 10:00:00', 'progress_percentage' => 25, 'id_rack' => 'C-7', 'order_type' => 'DELIVERY']);

    expect($dto->estimatedCompletion->format('Y-m-d H:i'))->toBe('2026-06-17 18:00')
        ->and($dto->estimatedCompletion->timezone->getName())->toBe('Asia/Jakarta')
        ->and($dto->isOpen())->toBeTrue()
        ->and(ActiveOrder::fromRow([])->estimatedCompletion)->toBeNull(); // null-safe
});

// ---------- OPS-1303 business-hours clock ----------
it('BusinessHoursClock: overdue lintas jam-tutup TAK dihitung penuh (anti false-positive)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    openHours(120); // 09-21
    $clk = app(BusinessHoursClock::class);
    // est 20:47 (D-1) → now 09:10 (D): operasional = 20:47-21:00 (13) + 09:00-09:10 (10) = 23 menit
    expect($clk->operationalMinutesBetween(120, \App\Support\Time\Wib::parse('2026-06-16 20:47'), \App\Support\Time\Wib::parse('2026-06-17 09:10')))->toBe(23);
});

// ---------- OPS-1303/1304 detector ----------
it('terlambat MAJOR (overdue > minor) → severity high', function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]); // wallclock
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/MAJOR', 'estimated_completion_date' => '2026-06-17 09:00:00'])]); // 180m overdue

    $s = app(LateOrderDetector::class)->detect(120, LNOW);
    $sig = SignalEvent::where('ref_transaction_number', 'INV/MAJOR')->first();
    expect($sig->type)->toBe('LATE_ORDER')->and($sig->severity)->toBe('high')
        ->and($sig->payload_json['tier'])->toBe('major')
        ->and($sig->payload_json['overdue_minutes'])->toBe(180);
});

it('terlambat MINOR (grace < overdue ≤ minor) → severity low (digest)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/MINOR', 'estimated_completion_date' => '2026-06-17 11:00:00'])]); // 60m

    app(LateOrderDetector::class)->detect(120, LNOW);
    expect(SignalEvent::where('ref_transaction_number', 'INV/MINOR')->first()->severity)->toBe('low');
});

it('dalam grace → tidak ada sinyal', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['estimated_completion_date' => '2026-06-17 11:45:00'])]); // 15m ≤ grace 30

    expect(app(LateOrderDetector::class)->detect(120, LNOW))->toHaveCount(0);
});

it('MACET (stuck): time-in-status > ambang, deadline belum lewat → high', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/STUCK', 'estimated_completion_date' => '2026-06-17 18:00:00', 'updated_at' => '2026-06-17 07:00:00', 'progress_percentage' => 10])]); // tis 300 > 240

    $s = app(LateOrderDetector::class)->detect(120, LNOW);
    expect(SignalEvent::where('ref_transaction_number', 'INV/STUCK')->first()->payload_json['tier'])->toBe('stuck');
});

it('APPROACHING (H-x sebelum deadline) → low/digest', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/APP', 'estimated_completion_date' => '2026-06-17 13:00:00', 'updated_at' => '2026-06-17 11:55:00'])]); // 60m menuju deadline

    app(LateOrderDetector::class)->detect(120, LNOW);
    $sig = SignalEvent::where('ref_transaction_number', 'INV/APP')->first();
    expect($sig->payload_json['tier'])->toBe('approaching')->and($sig->severity)->toBe('low');
});

it('status terminal & completion_date set → dikecualikan', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([
        lateOrder(['status' => 'DONE', 'estimated_completion_date' => '2026-06-17 09:00:00']),               // terminal
        lateOrder(['completion_date' => '2026-06-17 10:00:00', 'estimated_completion_date' => '2026-06-17 09:00:00']), // selesai
    ]);

    expect(app(LateOrderDetector::class)->detect(120, LNOW))->toHaveCount(0);
});

it('business_hours: nota lintas-tutup TAK terlambat (default mitigasi)', function () {
    Outlet::factory()->create(['id_outlet' => 121]);
    openHours(121); // 09-21
    OutletSlaConfig::factory()->create(['id_outlet' => 121, 'sla_clock_mode' => 'business_hours']);
    // est 20:47 D-1 → now 09:10 D: overdue operasional 23m ≤ grace 30 → tak ada LATE
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/12JAM', 'estimated_completion_date' => '2026-06-16 20:47:00', 'updated_at' => '2026-06-16 20:47:00'])]);

    expect(app(LateOrderDetector::class)->detect(121, '2026-06-17 09:10:00'))->toHaveCount(0);
});

it('payload TANPA PII pelanggan', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/P', 'estimated_completion_date' => '2026-06-17 09:00:00',
        'customer' => ['name' => 'Budi', 'phone' => '0812'], 'customer_name' => 'Budi'])]);

    app(LateOrderDetector::class)->detect(120, LNOW);
    $payload = SignalEvent::where('ref_transaction_number', 'INV/P')->first()->payload_json;
    $json = json_encode($payload);
    expect($json)->not->toContain('Budi')->not->toContain('0812')
        ->and($payload)->toHaveKeys(['tier', 'overdue_minutes', 'status_terakhir', 'time_in_status_minutes', 'id_rack']);
});

it('idempoten per nota per hari', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletSlaConfig::factory()->create(['id_outlet' => 120]);
    fakeLateOrders([lateOrder(['transaction_number' => 'INV/DUP', 'estimated_completion_date' => '2026-06-17 09:00:00'])]);

    app(LateOrderDetector::class)->detect(120, LNOW);
    app(LateOrderDetector::class)->detect(120, '2026-06-17 14:00:00');
    expect(SignalEvent::where('ref_transaction_number', 'INV/DUP')->count())->toBe(1);
});
