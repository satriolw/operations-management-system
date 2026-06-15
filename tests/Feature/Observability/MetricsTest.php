<?php

use App\Models\Outlet;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Reporting\Jobs\GenerateDailyReportJob;
use App\Support\Observability\Metrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => config([
    'cache.default' => 'array',
    'oms.metrics_cache_store' => 'array',
    'nevira.base_url' => 'https://api.nevira.id',
]));

it('counter increment & get', function () {
    Metrics::increment(Metrics::REPORTS_GENERATED);
    Metrics::increment(Metrics::REPORTS_GENERATED, 2);

    expect(Metrics::get(Metrics::REPORTS_GENERATED))->toBe(3);
});

it('latensi menghitung rata-rata, count, last', function () {
    Metrics::observeLatency('job.x', 100);
    Metrics::observeLatency('job.x', 200);

    expect(Metrics::latency('job.x'))->toMatchArray(['count' => 2, 'sum' => 300, 'avg' => 150.0, 'last' => 200]);
});

it('job sukses menaikkan reports_generated + idempoten tidak dobel', function () {
    Outlet::factory()->create(['id_outlet' => 120]);

    GenerateDailyReportJob::dispatchSync(120, '2026-06-12');
    GenerateDailyReportJob::dispatchSync(120, '2026-06-12'); // re-run idempoten

    expect(Metrics::get(Metrics::REPORTS_GENERATED))->toBe(1); // hanya sekali
});

it('panggilan NEVIRA terhitung (nevira_calls)', function () {
    // paksa ConfigTokenProvider (deterministik, tak bergantung .env)
    config([
        'nevira.service_username' => null,
        'nevira.service_password' => null,
        'nevira.token' => 'tok-static',
    ]);
    Http::fake(['*reports/dashboard*' => Http::response(['total_sales' => 1])]);

    app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');

    expect(Metrics::get(Metrics::NEVIRA_CALLS))->toBe(1);
});

it('kegagalan re-auth terhitung (nevira_reauth_failures)', function () {
    config([
        'nevira.service_username' => 'svc',
        'nevira.service_password' => 'pw',
        'nevira.auth.cache_store' => 'array',
    ]);
    Http::fake(['*/api/login' => Http::response('boom', 500)]);

    try {
        app(TransactionSource::class)->dailyDashboard(120, '2026-06-12');
    } catch (\Throwable $e) {
        // login gagal → NeviraAuthException
    }

    expect(Metrics::get(Metrics::NEVIRA_REAUTH_FAILURES))->toBeGreaterThanOrEqual(1);
});

it('command oms:metrics menampilkan counter (visibilitas)', function () {
    Metrics::increment(Metrics::NEVIRA_CALLS, 5);

    Artisan::call('oms:metrics', ['--json' => true]);
    $out = Artisan::output();

    expect($out)->toContain('nevira_calls')
        ->and(json_decode($out, true)['counters']['nevira_calls'])->toBe(5);
});
