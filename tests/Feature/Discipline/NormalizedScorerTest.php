<?php

use App\Models\ComplianceScore;
use App\Models\Outlet;
use App\Models\OutletCapacity;
use App\Modules\Discipline\LeaderboardMetrics;
use App\Modules\Discipline\NormalizedScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const W = ['growth' => 1.0, 'revenue_per_capacity' => 1.0, 'compliance' => 1.0];

it('min-max lintas outlet: nilai tertinggi → 100, terendah → 0', function () {
    $rows = [
        1 => ['growth' => 5, 'revenue_per_capacity' => null, 'compliance' => null],
        2 => ['growth' => 50, 'revenue_per_capacity' => null, 'compliance' => null],
    ];
    $out = app(NormalizedScorer::class)->compute($rows, W);

    expect($out[1]['score'])->toEqual(0.0)->and($out[2]['score'])->toEqual(100.0);
});

it('outlet kecil kompetitif: revenue PER KAPASITAS, bukan absolut', function () {
    // A: rev kecil tapi per-kapasitas tinggi; B: rev besar tapi per-kapasitas rendah.
    $rows = [
        1 => ['growth' => null, 'revenue_per_capacity' => 2500, 'compliance' => null], // outlet kecil
        2 => ['growth' => null, 'revenue_per_capacity' => 1250, 'compliance' => null], // outlet besar
    ];
    $out = app(NormalizedScorer::class)->compute($rows, W);

    expect($out[1]['score'])->toEqual(100.0)->and($out[2]['score'])->toEqual(0.0); // kecil menang
});

it('tahan kapasitas null (OPS-1101 belum ada): metrik dilewati, bobot di-redistribusi', function () {
    $rows = [
        1 => ['growth' => 10, 'revenue_per_capacity' => null, 'compliance' => 80],
        2 => ['growth' => 20, 'revenue_per_capacity' => null, 'compliance' => 40],
    ];
    $out = app(NormalizedScorer::class)->compute($rows, W);

    // hanya growth + compliance; outlet1 growth 0 + compliance 100 → 50; outlet2 growth 100 + compliance 0 → 50
    expect($out[1]['score'])->toEqual(50.0)->and($out[2]['score'])->toEqual(50.0)
        ->and($out[1]['components']['revenue_per_capacity'])->toBeNull();
});

it('bobot configurable mengubah skor', function () {
    $rows = [
        1 => ['growth' => 0, 'revenue_per_capacity' => null, 'compliance' => 100],
        2 => ['growth' => 100, 'revenue_per_capacity' => null, 'compliance' => 0],
    ];
    // tekankan compliance → outlet1 menang
    $out = app(NormalizedScorer::class)->compute($rows, ['growth' => 1, 'revenue_per_capacity' => 0, 'compliance' => 3]);
    expect($out[1]['score'])->toBeGreaterThan($out[2]['score']);
});

it('nilai sama → 100 (tak ada spread)', function () {
    $rows = [1 => ['growth' => 7, 'revenue_per_capacity' => null, 'compliance' => null], 2 => ['growth' => 7, 'revenue_per_capacity' => null, 'compliance' => null]];
    $out = app(NormalizedScorer::class)->compute($rows, W);
    expect($out[1]['score'])->toEqual(100.0)->and($out[2]['score'])->toEqual(100.0);
});

it('LeaderboardMetrics.build: growth, rev/kapasitas (OPS-1101), kepatuhan dari DB', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    OutletCapacity::factory()->create(['id_outlet' => 120, 'machines' => 4, 'throughput_kg_per_machine_hour' => 10, 'kg_per_day' => null, 'capacity_kg_per_hour' => null]); // 40 kg/jam
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-06', 'score' => 80]);

    Outlet::factory()->create(['id_outlet' => 121]); // tanpa kapasitas → rev_per_cap null

    $res = app(LeaderboardMetrics::class)->build(
        '2026-06',
        [120 => 200000, 121 => 150000],   // revenue periode
        [120 => 100000, 121 => 0],         // prior (121 prior 0 → growth null)
    );

    expect($res['rows'][120]['growth'])->toEqual(100.0)        // (200k-100k)/100k
        ->and($res['rows'][120]['revenue_per_capacity'])->toEqual(5000.0) // 200000/40
        ->and($res['rows'][120]['compliance'])->toEqual(80.0)
        ->and($res['rows'][121]['growth'])->toBeNull()          // prior 0
        ->and($res['rows'][121]['revenue_per_capacity'])->toBeNull(); // tanpa kapasitas
    expect($res['scores'][120]['score'])->toBeFloat();
});
