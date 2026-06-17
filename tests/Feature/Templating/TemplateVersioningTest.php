<?php

use App\Models\Outlet;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateVersion;
use App\Modules\Templating\TemplateVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->master = ReportTemplate::create([
        'scope' => 'master', 'name' => 'Harian', 'active' => true,
        'layout_json' => [['type' => 'kv', 'label' => 'Total', 'token' => 'total_sales']],
    ]);
    $this->v = app(TemplateVersioning::class);
});

it('saveDraft versi bertambah & tak mengubah live', function () {
    $d1 = $this->v->saveDraft($this->master, [['type' => 'kv', 'label' => 'A', 'token' => 'realized']]);
    $d2 = $this->v->saveDraft($this->master, [['type' => 'kv', 'label' => 'B', 'token' => 'piutang']]);

    expect($d1->version)->toBe(1)->and($d2->version)->toBe(2)
        ->and($d1->status)->toBe('draft');
    // live (template.layout_json) belum berubah
    expect($this->master->refresh()->layout_json[0]['token'])->toBe('total_sales');
});

it('draft token tak dikenal ditolak', function () {
    expect(fn () => $this->v->saveDraft($this->master, [['type' => 'kv', 'token' => 'ngaco']]))
        ->toThrow(InvalidArgumentException::class);
});

it('publish → versi jadi live, archive yang lama', function () {
    $d1 = $this->v->saveDraft($this->master, [['type' => 'kv', 'label' => 'A', 'token' => 'realized']]);
    $this->v->publish($d1);

    expect($this->master->refresh()->layout_json[0]['token'])->toBe('realized')
        ->and($d1->refresh()->status)->toBe('published');

    $d2 = $this->v->saveDraft($this->master, [['type' => 'kv', 'label' => 'B', 'token' => 'piutang']]);
    $this->v->publish($d2);

    expect($this->master->refresh()->layout_json[0]['token'])->toBe('piutang')
        ->and($d1->refresh()->status)->toBe('archived')      // lama di-archive
        ->and($d2->refresh()->status)->toBe('published');
});

it('rollback ke versi lama → live kembali ke versi itu', function () {
    $d1 = $this->v->saveDraft($this->master, [['type' => 'kv', 'token' => 'realized']]);
    $this->v->publish($d1);
    $d2 = $this->v->saveDraft($this->master, [['type' => 'kv', 'token' => 'piutang']]);
    $this->v->publish($d2);

    $this->v->rollback($this->master, 1); // kembali ke v1 (realized)

    expect($this->master->refresh()->layout_json[0]['token'])->toBe('realized')
        ->and(ReportTemplateVersion::where('version', 1)->first()->status)->toBe('published');
});

it('dampak publish master: outlet pewaris (tanpa override) terdaftar', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    // 121 punya override aktif → tak terdampak master
    ReportTemplate::create(['scope' => 'outlet', 'id_outlet' => 121, 'name' => 'Ovr', 'active' => true, 'layout_json' => []]);

    $impact = $this->v->impactOfMaster();
    expect($impact->all())->toContain(120)->and($impact->all())->not->toContain(121);
});
