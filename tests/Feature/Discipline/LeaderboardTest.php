<?php

use App\Models\ComplianceScore;
use App\Models\LeaderboardSnapshot;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Discipline\LeaderboardBuilder;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
});

it('ranking per periode (skor lebih tinggi → rank 1) + persist snapshot', function () {
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-06', 'score' => 80]);
    ComplianceScore::create(['id_outlet' => 121, 'period' => '2026-06', 'score' => 40]);

    $rows = app(LeaderboardBuilder::class)->build('2026-06', [120 => 100000, 121 => 100000], [120 => 0, 121 => 0]);

    $top = LeaderboardSnapshot::where('period', '2026-06')->orderBy('rank')->get();
    expect($top->first()->id_outlet)->toBe(120)->and($top->first()->rank)->toBe(1)
        ->and($top->last()->id_outlet)->toBe(121)->and($top->last()->rank)->toBe(2);
});

it('anti-gaming: rata-rata bergerak meredam lonjakan periode ini', function () {
    config(['discipline.leaderboard_moving_avg_periods' => 2]);
    // periode lalu: outlet 120 raw rendah (40)
    LeaderboardSnapshot::create(['period' => '2026-06', 'id_outlet' => 120, 'raw_score' => 40, 'score' => 40, 'rank' => 1]);
    // periode ini: 120 melonjak (compliance tinggi → komposit 100)
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-07', 'score' => 95]);

    app(LeaderboardBuilder::class)->build('2026-07', [120 => 100000], [120 => 0]);

    $snap = LeaderboardSnapshot::where(['period' => '2026-07', 'id_outlet' => 120])->first();
    expect((float) $snap->raw_score)->toEqual(100.0)   // komposit periode ini (single outlet → 100)
        ->and((float) $snap->score)->toEqual(70.0);     // (100 + 40) / 2 → diredam
});

it('idempoten: rebuild periode → updateOrCreate, tak menggandakan', function () {
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-06', 'score' => 80]);

    app(LeaderboardBuilder::class)->build('2026-06', [120 => 100000], [120 => 0]);
    app(LeaderboardBuilder::class)->build('2026-06', [120 => 100000], [120 => 0]);

    expect(LeaderboardSnapshot::where('period', '2026-06')->count())->toBe(1);
});

it('controller: Area Manager hanya lihat outlet binaannya', function () {
    LeaderboardSnapshot::create(['period' => '2026-06', 'id_outlet' => 120, 'raw_score' => 90, 'score' => 90, 'rank' => 1]);
    LeaderboardSnapshot::create(['period' => '2026-06', 'id_outlet' => 121, 'raw_score' => 50, 'score' => 50, 'rank' => 2]);

    $am = tap(User::factory()->create())->assignRole(Permissions::ROLE_AREA_MANAGER);
    $am->outlets()->attach(120);

    $this->actingAs($am)->get(route('discipline.leaderboard', ['period' => '2026-06']))
        ->assertOk()->assertSee('120')->assertDontSee('Outlet 121');
});

it('command oms:build-leaderboard membangun snapshot dari revenue NEVIRA', function () {
    config([
        'nevira.base_url' => 'https://api.nevira.id', 'nevira.token' => 'tok',
        'nevira.service_username' => null, 'nevira.service_password' => null,
    ]);
    Http::fake(['*' => Http::response(['total_sales' => 50000, 'txn_count' => 10])]);
    Outlet::query()->where('id_outlet', '!=', 120)->delete(); // 1 outlet → batasi loop fake
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-06', 'score' => 70]);

    $this->artisan('oms:build-leaderboard', ['--period' => '2026-06'])->assertSuccessful();

    expect(LeaderboardSnapshot::where('period', '2026-06')->where('id_outlet', 120)->exists())->toBeTrue();
});
