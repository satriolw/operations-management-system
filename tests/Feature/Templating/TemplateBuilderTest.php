<?php

use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Modules\Identity\Permissions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->template = ReportTemplate::create([
        'scope' => 'master', 'name' => 'Harian', 'active' => true,
        'layout_json' => [['type' => 'kv', 'label' => 'Total', 'token' => 'total_sales']],
    ]);
});

it('menolak akses builder tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);

    $this->actingAs($ops)->get(route('admin.templates.builder', $this->template))->assertForbidden();
    $this->actingAs($ops)->postJson(route('admin.templates.draft', $this->template), [])->assertForbidden();
});

it('halaman builder render 200 utk admin', function () {
    $this->actingAs(admin())->get(route('admin.templates.builder', $this->template))
        ->assertOk()->assertViewIs('admin.templates.builder');
});

it('saveDraft membuat versi draft (201) & tak ubah live', function () {
    $resp = $this->actingAs(admin())->postJson(route('admin.templates.draft', $this->template), [
        'layout_json' => [['type' => 'kv', 'label' => 'Realized', 'token' => 'realized']],
    ]);

    $resp->assertStatus(201)->assertJson(['version' => 1, 'status' => 'draft', 'fits_approved_template' => true]);
    expect(ReportTemplateVersion::where('report_template_id', $this->template->id)->count())->toBe(1);
    // live belum berubah
    expect($this->template->refresh()->layout_json[0]['token'])->toBe('total_sales');
});

it('layout_json bukan array → 422', function () {
    $this->actingAs(admin())->postJson(route('admin.templates.draft', $this->template), ['layout_json' => 'bukan-array'])
        ->assertStatus(422);
});

it('token tak dikenal → 422 (OPS-901)', function () {
    $this->actingAs(admin())->postJson(route('admin.templates.draft', $this->template), [
        'layout_json' => [['type' => 'kv', 'token' => 'ngaco']],
    ])->assertStatus(422);
});

it('R7: konten tak muat approved template → fits=false + warning', function () {
    config(['reporting.meta_param_max' => 10]); // paksa overflow

    $this->actingAs(admin())->postJson(route('admin.templates.draft', $this->template), [
        'layout_json' => [['type' => 'text', 'text' => 'Laporan harian outlet Kemang sangat panjang sekali']],
    ])->assertStatus(201)
        ->assertJson(['fits_approved_template' => false])
        ->assertJsonPath('warning', fn ($w) => is_string($w) && $w !== '');
});

it('publish versi → jadi live, fits dilaporkan', function () {
    $draft = ReportTemplateVersion::create([
        'report_template_id' => $this->template->id, 'version' => 1, 'status' => 'draft',
        'layout_json' => [['type' => 'kv', 'label' => 'Realized', 'token' => 'realized']],
    ]);

    $this->actingAs(admin())->postJson(route('admin.templates.publish', [$this->template, $draft]))
        ->assertOk()->assertJson(['published_version' => 1, 'fits_approved_template' => true]);

    expect($this->template->refresh()->layout_json[0]['token'])->toBe('realized')
        ->and($draft->refresh()->status)->toBe('published');
});

it('publish versi milik template lain → 404', function () {
    $other = ReportTemplate::create(['scope' => 'master', 'name' => 'Lain', 'active' => true, 'layout_json' => []]);
    $draft = ReportTemplateVersion::create([
        'report_template_id' => $other->id, 'version' => 1, 'status' => 'draft', 'layout_json' => [],
    ]);

    $this->actingAs(admin())->postJson(route('admin.templates.publish', [$this->template, $draft]))
        ->assertNotFound();
});
