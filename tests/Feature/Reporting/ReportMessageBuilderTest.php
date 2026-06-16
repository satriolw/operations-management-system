<?php

use App\Models\Outlet;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use App\Modules\Reporting\ReportMessageBuilder;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'Kemang']);
    $this->metrics = new DailyMetrics(
        outletId: 120, date: '2026-06-12', totalSales: 10138108,
        avgTransaction: 109012, avgCustomerSpending: 152329, txnCount: 93,
        volumes: ['kg' => 67, 'pcs' => 0],
    );
    $this->split = new RevenueSplit(totalSales: 10138108, realized: 9897108, receivable: 241000);
});

it('render pesan: placeholder, Rupiah, tanggal id-ID', function () {
    $text = app(ReportMessageBuilder::class)->build(120, '2026-06-12', $this->metrics, $this->split, [
        'nama_outlet' => 'Kemang', 'nama_investor' => 'Pak Andre',
    ]);

    expect($text)->toContain('Halo Pak Andre, berikut ringkasan Kemang · 12 Juni 2026.')
        ->and($text)->toContain('Total penjualan: Rp10.138.108')
        ->and($text)->toContain('Terealisasi: Rp9.897.108')
        ->and($text)->toContain('Piutang: Rp241.000')
        ->and($text)->toContain('Jumlah transaksi: 93')
        ->and($text)->toContain('Kg: 67');
});

it('hide-zero: volume_pcs 0 tidak muncul', function () {
    $text = app(ReportMessageBuilder::class)->build(120, '2026-06-12', $this->metrics, $this->split);
    expect($text)->not->toContain('Pcs');
});

it('blok penyesuaian revenue muncul hanya bila ada', function () {
    $tanpa = app(ReportMessageBuilder::class)->build(120, '2026-06-12', $this->metrics, $this->split);
    expect($tanpa)->not->toContain('Koreksi');

    $dengan = app(ReportMessageBuilder::class)->build(120, '2026-06-12', $this->metrics, $this->split, [
        'penyesuaian_revenue' => 'Koreksi -Rp430.000 (refund INV-00123, void INV-00119)',
    ]);
    expect($dengan)->toContain('Koreksi -Rp430.000');
});

it('memakai override outlet bila ada', function () {
    \App\Models\ReportTemplate::create([
        'scope' => 'outlet', 'id_outlet' => 120, 'name' => 'Override Kemang', 'active' => true,
        'layout_json' => [['type' => 'section', 'text' => 'RINGKAS KEMANG'], ['type' => 'kv', 'label' => 'Total', 'token' => 'total_sales']],
    ]);

    $text = app(ReportMessageBuilder::class)->build(120, '2026-06-12', $this->metrics, $this->split);
    expect($text)->toContain('RINGKAS KEMANG')->and($text)->toContain('Total: Rp10.138.108');
});
