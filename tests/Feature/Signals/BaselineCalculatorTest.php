<?php

use App\Models\Outlet;
use App\Models\OutletBaseline;
use App\Models\OutletHoliday;
use App\Models\ReportRun;
use App\Modules\Signals\BaselineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $o = Outlet::factory()->create(['id_outlet' => 120]);
    $o->checkpoints()->createMany([['check_time' => '11:00'], ['check_time' => '14:00']]);
});

function run(string $date, int $txn): void
{
    ReportRun::create(['id_outlet' => 120, 'report_date' => $date, 'status' => 'delivered', 'txn_count' => $txn]);
}

it('baseline rata-rata HANYA dari hari buka & bertransaksi (anti-bias)', function () {
    // 3 hari buka-bertransaksi: 80,100,90 → avg 90
    run('2026-07-01', 80);
    run('2026-07-02', 100);
    run('2026-07-03', 90);
    // hari nol-transaksi → dibuang
    run('2026-07-04', 0);
    // hari libur (meski txn>0) → dibuang
    run('2026-07-05', 200);
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-07-05']);

    $res = app(BaselineCalculator::class)->recompute(120, '2026-07-10');

    expect($res['avg'])->toBe(90.0)
        ->and($res['sample_days'])->toBe(3); // hanya 3 hari valid
});

it('baseline disimpan per titik cek dari KONFIGURASI outlet (bukan hardcode)', function () {
    run('2026-07-01', 50);
    app(BaselineCalculator::class)->recompute(120, '2026-07-10');

    expect(OutletBaseline::where('id_outlet', 120)->pluck('checkpoint_hour')->sort()->values()->all())->toBe([11, 14])
        ->and((float) OutletBaseline::where('checkpoint_hour', 11)->first()->avg_txn)->toBe(50.0);
});

it('hanya jendela 30 hari terakhir (hari lampau diabaikan)', function () {
    run('2026-05-01', 999); // > 30 hari sebelum today → diabaikan
    run('2026-07-09', 70);  // dalam jendela

    $res = app(BaselineCalculator::class)->recompute(120, '2026-07-10');
    expect($res['avg'])->toBe(70.0)->and($res['sample_days'])->toBe(1);
});

it('outlet baru (data minim) → sample_days kecil (fallback konservatif di OPS-502)', function () {
    run('2026-07-09', 60);
    $res = app(BaselineCalculator::class)->recompute(120, '2026-07-10');
    expect($res['sample_days'])->toBe(1);
});
