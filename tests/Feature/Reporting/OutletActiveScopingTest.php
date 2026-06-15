<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Modules\Reporting\Jobs\GenerateDailyReportJob;
use App\Support\Observability\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['cache.default' => 'array', 'oms.metrics_cache_store' => 'array']));

it('OPS-105: outlet NON-AKTIF → laporan tidak dibuat (tidak ada efek)', function () {
    Outlet::factory()->inactive()->create(['id_outlet' => 120]);

    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();

    expect(ReportRun::count())->toBe(0)
        ->and(Metrics::get(Metrics::REPORTS_GENERATED))->toBe(0);
});

it('OPS-105: outlet TIDAK TERDAFTAR → di-skip tanpa error / tanpa report_run', function () {
    // tak ada outlet 999
    (new GenerateDailyReportJob(999, '2026-06-12'))->handle();

    expect(ReportRun::count())->toBe(0);
});

it('OPS-105: outlet AKTIF → laporan tetap dibuat (sanity)', function () {
    Outlet::factory()->create(['id_outlet' => 120]); // active default true

    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();

    expect(ReportRun::where('id_outlet', 120)->count())->toBe(1)
        ->and(Metrics::get(Metrics::REPORTS_GENERATED))->toBe(1);
});
