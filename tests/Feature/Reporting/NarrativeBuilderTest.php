<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Modules\Reporting\NarrativeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => Outlet::factory()->create(['id_outlet' => 120]));

it('di atas rata-rata → kalimat positif', function () {
    $b = new NarrativeBuilder();
    expect($b->build(12000000, 10000000.0))->toContain('di atas rata-rata');
});

it('di bawah rata-rata → netral & jujur (tanpa klaim berlebih)', function () {
    $b = new NarrativeBuilder();
    $s = $b->build(8000000, 10000000.0);
    expect($s)->toContain('di bawah rata-rata')
        ->and(strtolower($s))->not->toContain('luar biasa')
        ->and(strtolower($s))->not->toContain('rekor');
});

it('data pembanding belum cukup → catatan netral', function () {
    expect((new NarrativeBuilder())->build(5000000, null))->toContain('belum cukup');
});

it('forOutlet menghitung rata-rata bulan berjalan (kecuali hari ini)', function () {
    ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-01', 'status' => 'delivered', 'total_sales' => 10000000]);
    ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-02', 'status' => 'delivered', 'total_sales' => 10000000]);
    // bulan lain → diabaikan
    ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-05-15', 'status' => 'delivered', 'total_sales' => 99000000]);

    $b = new NarrativeBuilder();
    expect($b->forOutlet(120, '2026-06-12', 12000000))->toContain('di atas rata-rata')   // 12jt > avg 10jt
        ->and($b->forOutlet(120, '2026-06-12', 8000000))->toContain('di bawah rata-rata');
});
