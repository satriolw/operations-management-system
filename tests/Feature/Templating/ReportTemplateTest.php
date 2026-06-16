<?php

use App\Models\Outlet;
use App\Models\ReportTemplate;
use App\Modules\Templating\TemplateResolver;
use App\Modules\Templating\TemplateTokens;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('token catalog: layout default semua token valid', function () {
    expect(TemplateTokens::isValid(DefaultTemplateSeeder::defaultLayout()))->toBeTrue();
});

it('token tak dikenal terdeteksi', function () {
    $layout = [['type' => 'kv', 'label' => 'X', 'token' => 'tidak_ada']];
    expect(TemplateTokens::isValid($layout))->toBeFalse()
        ->and(TemplateTokens::invalidTokens($layout))->toBe(['tidak_ada']);
});

it('token inline {{...}} di teks ikut tervalidasi', function () {
    $ok = [['type' => 'greeting', 'text' => 'Halo {{nama_investor}}']];
    $bad = [['type' => 'greeting', 'text' => 'Halo {{nama_bos}}']];
    expect(TemplateTokens::isValid($ok))->toBeTrue()
        ->and(TemplateTokens::isValid($bad))->toBeFalse();
});

it('seed default membuat satu master aktif & valid', function () {
    $this->seed(DefaultTemplateSeeder::class);

    $master = ReportTemplate::where('scope', 'master')->where('active', true)->first();
    expect($master)->not->toBeNull()
        ->and($master->hasValidTokens())->toBeTrue();
});

it('pewarisan: override outlet diutamakan atas master', function () {
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    $master = ReportTemplate::where('scope', 'master')->first();

    ReportTemplate::create([
        'scope' => 'outlet', 'parent_template_id' => $master->id, 'id_outlet' => 120,
        'name' => 'Override Kemang', 'active' => true,
        'layout_json' => [['type' => 'kv', 'label' => 'Total', 'token' => 'total_sales']],
    ]);

    $resolver = new TemplateResolver();
    expect($resolver->forOutlet(120)->name)->toBe('Override Kemang')
        ->and($resolver->forOutlet(999)->scope)->toBe('master'); // outlet lain → master
});

it('outlet tanpa override → mewarisi master', function () {
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 121]);

    expect((new TemplateResolver())->forOutlet(121)->scope)->toBe('master');
});
