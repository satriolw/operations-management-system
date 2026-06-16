<?php

use App\Models\ReportTemplate;
use App\Modules\Templating\TemplateRenderer;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Support\Facades\Log;

function defaultTemplate(): ReportTemplate
{
    return ReportTemplate::make(['scope' => 'master', 'name' => 't', 'layout_json' => DefaultTemplateSeeder::defaultLayout()]);
}

function sampleData(array $override = []): array
{
    return array_merge([
        'nama_investor' => 'Pak Andre', 'nama_outlet' => 'Kemang', 'tanggal' => '2026-06-12',
        'total_sales' => 10138108, 'realized' => 9897108, 'piutang' => 241000,
        'txn_count' => 93, 'avg_transaction' => 109012, 'volume_kg' => 67, 'volume_pcs' => 0,
    ], $override);
}

beforeEach(fn () => $this->r = new TemplateRenderer());

it('render teks hybrid: interpolasi, Rupiah & tanggal id-ID', function () {
    $text = $this->r->renderText(defaultTemplate(), sampleData());

    expect($text)->toContain('Halo Pak Andre, berikut ringkasan Kemang · 12 Juni 2026.')
        ->and($text)->toContain('Total penjualan: Rp10.138.108')
        ->and($text)->toContain('Piutang: Rp241.000')
        ->and($text)->toContain('Jumlah transaksi: 93')
        ->and($text)->toContain('Kg: 67');
});

it('hide-zero: metrik bernilai 0 disembunyikan', function () {
    $text = $this->r->renderText(defaultTemplate(), sampleData(['volume_pcs' => 0]));
    expect($text)->not->toContain('Pcs');
});

it('blok penyesuaian revenue muncul hanya bila ada koreksi', function () {
    $tanpa = $this->r->renderText(defaultTemplate(), sampleData());
    expect($tanpa)->not->toContain('Koreksi');

    $dengan = $this->r->renderText(defaultTemplate(), sampleData(['penyesuaian_revenue' => 'Koreksi -Rp430.000 (refund INV-00123)']));
    expect($dengan)->toContain('Koreksi -Rp430.000');
});

it('transport Opsi A: isi IDENTIK dgn hybrid, satu parameter besar, muat', function () {
    $tpl = defaultTemplate();
    $data = sampleData();

    $hybrid = $this->r->renderText($tpl, $data);
    $t = $this->r->forTransport($tpl, $data);

    expect($t['fits'])->toBeTrue()
        ->and($t['params']['1'])->toBe($hybrid)   // konten identik
        ->and($t['reason'])->toBeNull();
});

it('guard: konten melebihi kapasitas → fits=false + reason + alert log', function () {
    $logged = '';
    Log::listen(function ($e) use (&$logged) { $logged .= ' '.$e->message; });

    $t = $this->r->forTransport(defaultTemplate(), sampleData(), max: 10);

    expect($t['fits'])->toBeFalse()
        ->and($t['reason'])->not->toBeNull();
    expect($logged)->toContain('template.transport_overflow'); // alert → pemanggil fallback hybrid
});
