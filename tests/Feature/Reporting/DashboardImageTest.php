<?php

use App\Modules\Reporting\BrowsershotRenderer;
use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Modules\Reporting\DashboardCardHtml;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;

function sampleMetrics(array $vol = ['kg' => 67, 'pcs' => 0]): DailyMetrics
{
    return new DailyMetrics(
        outletId: 120, date: '2026-06-12', totalSales: 10138108,
        avgTransaction: 109012, avgCustomerSpending: 152329, txnCount: 93, volumes: $vol,
    );
}
function sampleSplit(int $piutang = 241000): RevenueSplit
{
    return new RevenueSplit(totalSales: 10138108, realized: 10138108 - $piutang, receivable: $piutang);
}

it('HTML kartu memuat angka OPS-201/202 (Rupiah, tanggal id-ID)', function () {
    $html = app(DashboardCardHtml::class)->build(sampleMetrics(), sampleSplit(), '2026-06-12', [
        'nama_outlet' => 'Kemang', 'nama_investor' => 'Pak Andre',
    ]);

    expect($html)->toContain('Kemang')
        ->and($html)->toContain('Rp10.138.108')   // total
        ->and($html)->toContain('Rp9.897.108')      // terealisasi
        ->and($html)->toContain('Rp241.000')        // piutang
        ->and($html)->toContain('93')               // transaksi
        ->and($html)->toContain('Jumat, 12 Juni 2026'); // tanggal id-ID
});

it('hide-zero: volume_pcs 0 & piutang 0 disembunyikan', function () {
    $html = app(DashboardCardHtml::class)->build(sampleMetrics(['kg' => 67, 'pcs' => 0]), sampleSplit(0), '2026-06-12');

    expect($html)->toContain('67')
        ->and($html)->not->toContain('Pcs')   // volume 0 disembunyikan
        ->and($html)->not->toContain('Piutang'); // piutang 0 → box disembunyikan
});

it('render deterministik (snapshot): input sama → HTML sama', function () {
    $a = app(DashboardCardHtml::class)->build(sampleMetrics(), sampleSplit(), '2026-06-12', ['nama_outlet' => 'Kemang']);
    $b = app(DashboardCardHtml::class)->build(sampleMetrics(), sampleSplit(), '2026-06-12', ['nama_outlet' => 'Kemang']);

    expect($a)->toBe($b);
});

it('binding default DashboardImageRenderer = BrowsershotRenderer', function () {
    expect(app(DashboardImageRenderer::class))->toBeInstanceOf(BrowsershotRenderer::class);
});

it('Browsershot menghasilkan PNG bernomor (butuh Chromium)', function () {
    $node = trim((string) shell_exec('command -v node 2>/dev/null'));
    if ($node === '' || env('OMS_TEST_BROWSERSHOT') !== '1') {
        $this->markTestSkipped('Lewati: Node/Chromium tak tersedia di env ini (set OMS_TEST_BROWSERSHOT=1 bila ada).');
    }

    $path = sys_get_temp_dir().'/oms-card-'.uniqid().'.png';
    app(DashboardImageRenderer::class)->render(sampleMetrics(), sampleSplit(), '2026-06-12', ['nama_outlet' => 'Kemang'], $path);

    expect(file_exists($path))->toBeTrue()
        ->and(mime_content_type($path))->toBe('image/png');
    @unlink($path);
});
