<?php

use App\Models\Outlet;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Modules\Identity\Permissions;
use App\Modules\Templating\TemplateTokens;
use Database\Seeders\DefaultTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->seed(DefaultTemplateSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120, 'name' => 'LW Kemang']);
    $this->master = ReportTemplate::where('scope', 'master')->first();
});

it('non-admin ditolak (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.templates.index'))->assertForbidden();
});

it('index menampilkan master + bagian override', function () {
    $this->actingAs(admin())->get(route('admin.templates.index'))->assertOk()
        ->assertSee($this->master->name)->assertSee('Template master')->assertSee('Override per-outlet');
});

it('buat master → layout default valid + redirect ke builder', function () {
    $this->actingAs(admin())->post(route('admin.templates.store'), ['scope' => 'master', 'name' => 'Master B2B'])
        ->assertRedirect();

    $tpl = ReportTemplate::where('name', 'Master B2B')->first();
    expect($tpl)->not->toBeNull()
        ->and($tpl->scope)->toBe('master')
        ->and(TemplateTokens::isValid($tpl->layout_json))->toBeTrue(); // pipeline tak terblokir
});

it('override outlet mewarisi layout master', function () {
    $this->actingAs(admin())->post(route('admin.templates.store'), [
        'scope' => 'outlet', 'name' => 'Override Kemang', 'id_outlet' => 120, 'parent_template_id' => $this->master->id,
    ])->assertRedirect();

    $ovr = ReportTemplate::where('name', 'Override Kemang')->first();
    expect($ovr->id_outlet)->toBe(120)
        ->and($ovr->parent_template_id)->toBe($this->master->id)
        ->and($ovr->layout_json)->toBe($this->master->layout_json);
});

it('override tanpa id_outlet/parent → ditolak validasi', function () {
    $this->actingAs(admin())->from(route('admin.templates.index'))
        ->post(route('admin.templates.store'), ['scope' => 'outlet', 'name' => 'Tanpa Outlet'])
        ->assertSessionHasErrors(['id_outlet', 'parent_template_id']);
    expect(ReportTemplate::where('name', 'Tanpa Outlet')->exists())->toBeFalse();
});

it('master yang masih diwarisi override tak bisa dihapus', function () {
    ReportTemplate::create(['scope' => 'outlet', 'parent_template_id' => $this->master->id, 'id_outlet' => 120,
        'name' => 'Ovr', 'active' => true, 'layout_json' => $this->master->layout_json]);

    $this->actingAs(admin())->from(route('admin.templates.index'))
        ->delete(route('admin.templates.destroy', $this->master))->assertSessionHasErrors('template');
    expect(ReportTemplate::find($this->master->id))->not->toBeNull();
});

it('hapus template (tanpa anak) berhasil', function () {
    $solo = ReportTemplate::create(['scope' => 'master', 'name' => 'Solo', 'active' => true, 'layout_json' => DefaultTemplateSeeder::defaultLayout()]);
    $this->actingAs(admin())->from(route('admin.templates.index'))
        ->delete(route('admin.templates.destroy', $solo))->assertRedirect();
    expect(ReportTemplate::find($solo->id))->toBeNull();
});
