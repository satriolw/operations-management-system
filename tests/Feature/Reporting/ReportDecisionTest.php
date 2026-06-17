<?php

use App\Models\Outlet;
use App\Models\OutletHoliday;
use App\Models\OutletOperatingHour;
use App\Models\ReportRun;
use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use App\Modules\Reporting\OutletCalendar;
use App\Modules\Reporting\ReportComposer;
use App\Modules\Reporting\ReportDecider;
use App\Modules\Reporting\ReportDecision;
use App\Support\Observability\Events\OpsAlertRaised;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
        'cache.default' => 'array', 'oms.metrics_cache_store' => 'array',
    ]);
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Kemang']);

    app()->instance(DashboardImageRenderer::class, new class implements DashboardImageRenderer
    {
        public function render(DailyMetrics $m, RevenueSplit $s, string $d, array $c, string $o): string
        {
            return '/fake.png';
        }
    });
});

function fakeNeviraDash(int $total, int $txn): void
{
    Http::fake([
        '*reports/dashboard*' => Http::response(['total_sales' => $total, 'avg_transaction' => $total ? (int) ($total / max($txn, 1)) : 0, 'txn_count' => $txn]),
        '*payment_status=UNPAID*' => Http::response(['data' => [], 'last_page' => 1, 'current_page' => 1]),
    ]);
}

// --- unit: calendar & decider ---
it('OutletCalendar: hari libur → tutup', function () {
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-06-12', 'note' => 'Libur']);
    expect(app(OutletCalendar::class)->isClosed(120, '2026-06-12'))->toBeTrue();
});

it('OutletCalendar: weekday is_closed → tutup; tak terkonfigurasi → buka', function () {
    OutletOperatingHour::create(['id_outlet' => 120, 'weekday' => 5, 'is_closed' => true]); // Jumat
    expect(app(OutletCalendar::class)->isClosed(120, '2026-06-12'))->toBeTrue()   // 12 Jun 2026 = Jumat
        ->and(app(OutletCalendar::class)->isClosed(120, '2026-06-11'))->toBeFalse(); // Kamis, tak dikonfigurasi
});

it('ReportDecider: tutup→suppress, buka-nol→empty, ada transaksi→normal', function () {
    $d = app(ReportDecider::class);
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-06-12']);
    expect($d->decide(120, '2026-06-12', 0)->action)->toBe(ReportDecision::SUPPRESS)
        ->and($d->decide(120, '2026-06-11', 0)->action)->toBe(ReportDecision::EMPTY_STATE)
        ->and($d->decide(120, '2026-06-11', 93)->action)->toBe(ReportDecision::NORMAL);
});

// --- integrasi composer ---
it('SUPPRESS: outlet tutup/libur → tidak ada report_run', function () {
    OutletHoliday::create(['id_outlet' => 120, 'holiday_date' => '2026-06-12']);
    fakeNeviraDash(0, 0);

    $run = app(ReportComposer::class)->compose(120, '2026-06-12');

    expect($run)->toBeNull()
        ->and(ReportRun::where('id_outlet', 120)->count())->toBe(0);
});

it('EMPTY-STATE: buka-nol → tetap kirim dgn catatan + alert internal + sinyal', function () {
    Event::fake([OpsAlertRaised::class]);
    fakeNeviraDash(0, 0); // buka, nol transaksi

    $run = app(ReportComposer::class)->compose(120, '2026-06-12', ['nama_outlet' => 'Kemang']);

    expect($run)->not->toBeNull()
        ->and($run->payload_text)->toContain('belum ada transaksi tercatat');
    Event::assertDispatched(OpsAlertRaised::class, fn ($e) => $e->code === 'outlet.silent_zero');
});

it('NORMAL: ada transaksi → laporan biasa tanpa catatan empty', function () {
    fakeNeviraDash(10000000, 80);

    $run = app(ReportComposer::class)->compose(120, '2026-06-12');

    expect($run)->not->toBeNull()
        ->and($run->payload_text)->not->toContain('belum ada transaksi tercatat')
        ->and($run->txn_count)->toBe(80);
});
