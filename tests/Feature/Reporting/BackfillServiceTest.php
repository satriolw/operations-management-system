<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Modules\Reporting\BackfillService;
use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Kemang']);
    Http::fake([
        '*reports/dashboard*' => Http::response(['total_sales' => 10000000, 'avg_transaction' => 125000, 'txn_count' => 80]),
        '*payment_status=UNPAID*' => Http::response(['data' => [], 'last_page' => 1, 'current_page' => 1]),
    ]);
    app()->instance(DashboardImageRenderer::class, new class implements DashboardImageRenderer
    {
        public function render(DailyMetrics $m, RevenueSplit $s, string $d, array $c, string $o): string
        {
            return '/fake.png';
        }
    });
});

it('backfill nyata: simpan report_run tanggal lampau, idempoten', function () {
    app(BackfillService::class)->run(120, '2026-05-01');
    app(BackfillService::class)->run(120, '2026-05-01'); // replay

    expect(ReportRun::where('id_outlet', 120)->where('report_date', '2026-05-01')->count())->toBe(1);
});

it('dry-run: preview teks TANPA persist', function () {
    $r = app(BackfillService::class)->run(120, '2026-05-01', dryRun: true, context: ['nama_outlet' => 'Kemang']);

    expect($r['dry_run'])->toBeTrue()
        ->and($r['persisted'])->toBeFalse()
        ->and($r['text'])->toContain('Rp10.000.000')
        ->and(ReportRun::count())->toBe(0); // tak ada yang disimpan
});

it('command oms:report-backfill --dry-run jalan tanpa persist', function () {
    $this->artisan('oms:report-backfill', ['outlet' => 120, 'date' => '2026-05-01', '--dry-run' => true])->assertSuccessful();
    expect(ReportRun::count())->toBe(0);
});
