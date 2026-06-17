<?php

use App\Modules\Revenue\DTO\Correction;
use App\Modules\Revenue\DTO\RestateSummary;
use App\Modules\Revenue\RevenueAdjustmentBlockRenderer;

function corr(string $no, string $type, int $amount, ?string $reason): Correction
{
    return new Correction($no, $type, $amount, $reason, '2026-06-10', '2026-06-12');
}

beforeEach(fn () => $this->r = new RevenueAdjustmentBlockRenderer());

it('blok absen (null) bila tidak ada koreksi', function () {
    expect($this->r->render(collect(), new RestateSummary([], 0)))->toBeNull();
});

it('render blok: nota, jenis, nominal, alasan, total, revenue lama→baru', function () {
    $corrections = collect([
        corr('INV/00123', 'REFUND', 250000, 'hasil cuci dikomplain'),
        corr('INV/00119', 'VOID', 180000, 'salah input'),
    ]);
    $summary = new RestateSummary(
        ['2026-06-10' => ['old' => 9200000, 'correction' => 430000, 'new' => 8770000, 'count' => 2]],
        430000,
    );

    $block = $this->r->render($corrections, $summary);

    expect($block)->toContain('PENYESUAIAN REVENUE')
        ->and($block)->toContain('INV/00123 (10 Jun) Refund Rp250.000 — "hasil cuci dikomplain"')
        ->and($block)->toContain('INV/00119 (10 Jun) Void Rp180.000 — "salah input"')
        ->and($block)->toContain('Total koreksi: -Rp430.000')
        ->and($block)->toContain('Revenue 10 Jun: Rp9.200.000 → Rp8.770.000');
});

it('tanpa report_run lama (old null) → baris revenue lama→baru disembunyikan', function () {
    $block = $this->r->render(
        collect([corr('INV/X', 'VOID', 50000, null)]),
        new RestateSummary(['2026-06-10' => ['old' => null, 'correction' => 50000, 'new' => null, 'count' => 1]], 50000),
    );

    expect($block)->toContain('Total koreksi: -Rp50.000')
        ->and($block)->not->toContain('→');
});
