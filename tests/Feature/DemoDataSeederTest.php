<?php

use App\Models\FinancialDocument;
use App\Models\LeaderboardSnapshot;
use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\SignalEvent;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    tap(User::factory()->create(['email' => 'satrio@lessworry.id']))->assignRole(\App\Modules\Identity\Permissions::ROLE_ADMIN);
    Outlet::factory()->create(['id_outlet' => 115, 'name' => 'Kemang']);
    Outlet::factory()->create(['id_outlet' => 116, 'name' => 'Cipete']);
});

it('membuat data demo (sinyal/laporan/dokumen/leaderboard) — idempoten', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class); // idempoten

    expect(SignalEvent::where('status', 'OPEN')->count())->toBeGreaterThan(0)
        ->and(ReportRun::count())->toBeGreaterThan(0)
        ->and(FinancialDocument::count())->toBeGreaterThan(0)
        ->and(LeaderboardSnapshot::count())->toBeGreaterThan(0);
    // tak menggandakan (idempoten)
    expect(FinancialDocument::where('doc_number', '260618-LW15/RF/OPS/001')->count())->toBe(1);
});

it('dashboard berisi setelah data demo (bukan "Operasional bersih")', function () {
    $this->seed(DemoDataSeeder::class);
    $admin = User::where('email', 'satrio@lessworry.id')->first();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertDontSee('Operasional bersih');
});

it('di-skip di production (bukan data palsu permanen)', function () {
    $this->app->detectEnvironment(fn () => 'production');

    (new DemoDataSeeder())->run(); // langsung (hindari prompt produksi Artisan db:seed)

    expect(SignalEvent::count())->toBe(0)->and(ReportRun::count())->toBe(0);
});
