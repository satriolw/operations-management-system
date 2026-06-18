<?php

use App\Models\Outlet;
use App\Models\OutletOperatingHour;
use App\Modules\Ingestion\Contracts\TransactionSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Sumber NEVIRA di-mock → activeOrders kosong (detector tak hasilkan sinyal); fokus uji = gating.
    $this->mock(TransactionSource::class, function ($m) {
        $m->shouldReceive('activeOrders')->andReturn(collect());
    });

    Outlet::factory()->create(['id_outlet' => 120]); // buka Senin 08–20
    Outlet::factory()->create(['id_outlet' => 130]); // tutup Senin
    OutletOperatingHour::create(['id_outlet' => 120, 'weekday' => 1, 'is_closed' => false, 'open_time' => '08:00', 'close_time' => '20:00']);
    OutletOperatingHour::create(['id_outlet' => 130, 'weekday' => 1, 'is_closed' => true]);
});

it('hanya poll outlet yang buka sekarang (skip yang tutup)', function () {
    $this->artisan('oms:check-late-orders', ['--date' => '2026-06-15 10:00:00'])
        ->expectsOutputToContain('1 outlet dipoll')
        ->assertSuccessful();
});

it('watermark mencegah poll ulang dalam cadence', function () {
    config(['nevira.poll_cadence' => ['late_orders' => 15, 'default' => 15]]);

    $this->artisan('oms:check-late-orders', ['--date' => '2026-06-15 10:00:00'])->expectsOutputToContain('1 outlet dipoll');
    // run kedua waktu sama → watermark masih segar → 0 dipoll
    $this->artisan('oms:check-late-orders', ['--date' => '2026-06-15 10:05:00'])->expectsOutputToContain('0 outlet dipoll');
});

it('--force mengabaikan watermark & jam (manual)', function () {
    $this->artisan('oms:check-late-orders', ['--date' => '2026-06-15 10:00:00']);
    // pakai force pada outlet yang seharusnya tertutup watermark → tetap jalan (2 outlet: termasuk yang tutup)
    $this->artisan('oms:check-late-orders', ['--date' => '2026-06-15 10:05:00', '--force' => true])
        ->expectsOutputToContain('2 outlet dipoll');
});
