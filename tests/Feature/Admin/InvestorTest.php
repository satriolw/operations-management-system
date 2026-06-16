<?php

use App\Models\DeliveryTarget;
use App\Models\Investor;
use App\Models\Outlet;
use App\Models\WhatsappAccount;
use App\Modules\Reporting\ReportPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('investor master ringan 1:1 outlet + relasi outlet', function () {
    $outlet = Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Kemang']);
    $inv = Investor::create([
        'name' => 'Pak Andre', 'wa_contact' => '+628120000000', 'id_outlet' => 120,
        'since_date' => '2025-01-01',
    ]);

    expect($inv->outlet->name)->toBe('Kemang');
});

it('1:1 outlet ditegakkan (investor kedua utk outlet sama ditolak)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    Investor::create(['name' => 'A', 'id_outlet' => 120]);

    expect(fn () => Investor::create(['name' => 'B', 'id_outlet' => 120]))
        ->toThrow(QueryException::class);
});

it('delivery_target tertaut ke investor', function () {
    $outlet = Outlet::factory()->create(['id_outlet' => 120]);
    $inv = Investor::create(['name' => 'Pak Andre', 'id_outlet' => 120]);
    $target = DeliveryTarget::factory()->create([
        'id_outlet' => 120, 'investor_id' => $inv->id, 'whatsapp_account_id' => WhatsappAccount::factory(),
    ]);

    expect($target->investor->name)->toBe('Pak Andre')
        ->and($inv->deliveryTargets()->count())->toBe(1);
});

it('periode = hari kalender penuh WIB', function () {
    $w = ReportPeriod::dayWindow('2026-06-12');

    expect($w['start']->format('Y-m-d H:i:s'))->toBe('2026-06-12 00:00:00')
        ->and($w['end']->format('Y-m-d H:i:s'))->toBe('2026-06-12 23:59:59')
        ->and($w['start']->timezoneName)->toBe('Asia/Jakarta')
        ->and(ReportPeriod::isCalendarDay())->toBeTrue();
});

it('cutoff: laporan dikirim setelah hari ditutup (WIB)', function () {
    config(['reporting.cutoff_time' => '23:59']);
    $after = ReportPeriod::sendAfter('2026-06-12');

    expect($after->format('Y-m-d H:i'))->toBe('2026-06-12 23:59')
        ->and($after->timezoneName)->toBe('Asia/Jakarta');
});

it('investors tak punya kolom PII customer', function () {
    $cols = \Illuminate\Support\Facades\Schema::getColumnListing('investors');
    foreach (['phone', 'telepon', 'alamat', 'address', 'email'] as $bad) {
        expect(in_array($bad, $cols, true))->toBeFalse();
    }
});
