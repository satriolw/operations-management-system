<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\SignalEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    config(['retention.report_payload_days' => 90, 'retention.signal_payload_days' => 180]);
});

/** Set created_at tanpa menyentuh kolom lain. */
function ageReportRun(int $id, int $days): void
{
    ReportRun::where('id', $id)->update(['created_at' => now()->subDays($days)]);
}

it('membersihkan payload report_runs melewati ambang umur, menyisakan yang baru', function () {
    $old = ReportRun::create([
        'id_outlet' => 120, 'report_date' => '2026-03-01', 'status' => 'delivered',
        'payload_text' => 'teks laporan besar', 'image_path' => 'dash/old.png',
        'total_sales' => 1000000, 'txn_count' => 50,
    ]);
    ageReportRun($old->id, 100); // > 90 hari

    $recent = ReportRun::create([
        'id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'delivered',
        'payload_text' => 'simpan ini', 'image_path' => 'dash/new.png',
    ]); // created_at = sekarang

    Artisan::call('oms:purge-raw-payloads');

    $old->refresh();
    $recent->refresh();

    // lama: payload mentah dinolkan, angka turunan & referensi TETAP (LBE-ready)
    expect($old->payload_text)->toBeNull()
        ->and($old->image_path)->toBeNull()
        ->and((int) $old->total_sales)->toBe(1000000)
        ->and($old->txn_count)->toBe(50)
        ->and($old->status)->toBe('delivered');

    // baru: utuh
    expect($recent->payload_text)->toBe('simpan ini')
        ->and($recent->image_path)->toBe('dash/new.png');
});

it('mengosongkan payload_json signal_events melewati ambang umur', function () {
    $old = SignalEvent::create([
        'id_outlet' => 120, 'type' => 'SELF_APPROVAL', 'severity' => 'high',
        'ref_transaction_number' => 'INV/120/8134', 'id_cashier' => 181,
        'payload_json' => ['amount' => 81225, 'reason' => 'x'],
        'status' => 'OPEN', 'detected_at' => now()->subDays(200),
    ]);
    $recent = SignalEvent::create([
        'id_outlet' => 120, 'type' => 'SELF_APPROVAL', 'severity' => 'high',
        'payload_json' => ['amount' => 5000], 'status' => 'OPEN', 'detected_at' => now()->subDays(10),
    ]);

    Artisan::call('oms:purge-raw-payloads');

    expect($old->fresh()->payload_json)->toBeNull()
        ->and($recent->fresh()->payload_json)->toBe(['amount' => 5000]);
    // baris + referensi tetap ada untuk audit
    expect($old->fresh()->ref_transaction_number)->toBe('INV/120/8134');
});

it('command idempoten: jalan kedua tak error & tak ada yang berubah lagi', function () {
    $old = ReportRun::create([
        'id_outlet' => 120, 'report_date' => '2026-03-01', 'status' => 'delivered',
        'payload_text' => 'x',
    ]);
    ageReportRun($old->id, 100);

    Artisan::call('oms:purge-raw-payloads');
    $code = Artisan::call('oms:purge-raw-payloads');

    expect($code)->toBe(0)
        ->and($old->fresh()->payload_text)->toBeNull();
});
