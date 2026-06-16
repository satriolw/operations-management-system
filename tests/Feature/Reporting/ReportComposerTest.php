<?php

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use App\Modules\Reporting\ReportComposer;
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
        '*reports/dashboard*' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/nevira/dashboard.json')), true)),
        '*payment_status=UNPAID*' => Http::response(['data' => [], 'last_page' => 1, 'current_page' => 1]),
    ]);

    // Fake image renderer (hindari Chromium): kembalikan path.
    app()->instance(DashboardImageRenderer::class, new class implements DashboardImageRenderer
    {
        public function render(DailyMetrics $m, RevenueSplit $s, string $date, array $ctx, string $out): string
        {
            return '/fake/card.png';
        }
    });
});

it('compose menyimpan report_run: teks + total + gambar, status generated', function () {
    $run = app(ReportComposer::class)->compose(120, '2026-06-12', ['nama_outlet' => 'Kemang', 'nama_investor' => 'Pak Andre']);

    expect($run->status)->toBe('generated')
        ->and($run->payload_text)->toContain('Rp12.500.000')   // total dari dashboard fixture
        ->and((int) $run->total_sales)->toBe(12500000)
        ->and((int) $run->realized)->toBe(12500000)             // piutang 0 → realized = total
        ->and($run->txn_count)->toBe(80)
        ->and($run->image_path)->toBe('/fake/card.png');
});

it('idempoten: compose dua kali → satu report_run', function () {
    app(ReportComposer::class)->compose(120, '2026-06-12');
    app(ReportComposer::class)->compose(120, '2026-06-12');

    expect(ReportRun::where('id_outlet', 120)->where('report_date', '2026-06-12')->count())->toBe(1);
});

it('blok Penyesuaian Revenue tampil hanya bila ada koreksi', function () {
    $tanpa = app(ReportComposer::class)->compose(120, '2026-06-12');
    expect($tanpa->payload_text)->not->toContain('Koreksi');

    $dengan = app(ReportComposer::class)->compose(120, '2026-06-12', [], 'Koreksi -Rp430.000 (refund INV-00123)');
    expect($dengan->payload_text)->toContain('Koreksi -Rp430.000');
});

it('toleran gambar: render PNG gagal → image_path null, laporan teks tetap tersimpan', function () {
    app()->instance(DashboardImageRenderer::class, new class implements DashboardImageRenderer
    {
        public function render(DailyMetrics $m, RevenueSplit $s, string $date, array $ctx, string $out): string
        {
            throw new \RuntimeException('Chromium tak ada');
        }
    });

    $run = app(ReportComposer::class)->compose(120, '2026-06-12');

    expect($run->image_path)->toBeNull()
        ->and($run->payload_text)->toContain('Rp12.500.000'); // teks tetap ada
});

it('payload dapat di-preview sebelum kirim (status belum delivered)', function () {
    $run = app(ReportComposer::class)->compose(120, '2026-06-12');
    expect($run->status)->not->toBe('delivered')
        ->and($run->payload_text)->not->toBeEmpty();
});
