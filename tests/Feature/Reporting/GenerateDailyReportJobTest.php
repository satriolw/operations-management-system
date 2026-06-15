<?php

use App\Models\Outlet;
use App\Models\ReportDelivery;
use App\Models\ReportRun;
use App\Modules\Reporting\Jobs\GenerateDailyReportJob;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;

uses(RefreshDatabase::class);

beforeEach(fn () => Outlet::factory()->create(['id_outlet' => 120, 'report_time' => '21:00']));

it('dispatch dua kali untuk (outlet, tanggal) sama → hanya satu report_run (idempoten)', function () {
    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();
    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();

    expect(ReportRun::where('id_outlet', 120)->where('report_date', '2026-06-12')->count())->toBe(1);
    expect(ReportRun::first()->status)->toBe('generated');
});

it('re-run TIDAK menimpa efek run yang sudah selesai (no double effect)', function () {
    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();

    // anggap sudah terkirim
    ReportRun::first()->update(['status' => 'delivered']);

    // replay/re-run → harus skip, tidak reset ke 'generated'
    (new GenerateDailyReportJob(120, '2026-06-12'))->handle();

    expect(ReportRun::first()->status)->toBe('delivered')
        ->and(ReportRun::count())->toBe(1);
});

it('dispatchSync (via queue + middleware) tetap idempoten', function () {
    GenerateDailyReportJob::dispatchSync(120, '2026-06-12');
    GenerateDailyReportJob::dispatchSync(120, '2026-06-12');

    expect(ReportRun::where('id_outlet', 120)->where('report_date', '2026-06-12')->count())->toBe(1);
});

it('default report_date = hari ini (WIB) bila tidak diberikan', function () {
    (new GenerateDailyReportJob(120))->handle();

    $today = \App\Support\Time\Wib::normalize(now())->format('Y-m-d');
    expect(ReportRun::where('id_outlet', 120)->where('report_date', $today)->count())->toBe(1);
});

it('job async + retryable: ShouldQueue, tries=3, backoff bertingkat', function () {
    $job = new GenerateDailyReportJob(120, '2026-06-12');

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([60, 300, 900]);
});

it('WithoutOverlapping per outlet terpasang sebagai middleware', function () {
    $mw = (new GenerateDailyReportJob(120, '2026-06-12'))->middleware();

    expect($mw)->toHaveCount(1)
        ->and($mw[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('idempotency delivery: dua kiriman (report_run, channel) sama ditolak unik', function () {
    $run = ReportRun::create(['id_outlet' => 120, 'report_date' => '2026-06-12', 'status' => 'generated']);

    ReportDelivery::create([
        'report_run_id' => $run->id, 'id_outlet' => 120, 'channel' => 'hybrid',
        'status' => 'sent', 'idempotency_key' => IdempotencyKey::delivery($run->id, 'hybrid'),
    ]);

    // channel sama untuk run sama → langgar unique(report_run_id, channel)
    expect(fn () => ReportDelivery::create([
        'report_run_id' => $run->id, 'id_outlet' => 120, 'channel' => 'hybrid',
        'status' => 'sent', 'idempotency_key' => 'delivery:'.$run->id.':hybrid:dup',
    ]))->toThrow(QueryException::class);

    expect(ReportDelivery::where('report_run_id', $run->id)->count())->toBe(1);
});

it('IdempotencyKey menghasilkan kunci kanonik', function () {
    expect(IdempotencyKey::reportRun(120, '2026-06-12'))->toBe('report:120:2026-06-12')
        ->and(IdempotencyKey::delivery(5, 'hybrid'))->toBe('delivery:5:hybrid');
});
