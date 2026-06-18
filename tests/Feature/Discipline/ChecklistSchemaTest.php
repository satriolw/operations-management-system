<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class));

it('membuat semua tabel Modul 3 (skema M3-01)', function () {
    foreach (['checklist_templates', 'checklist_items', 'checklist_runs', 'checklist_submissions'] as $t) {
        expect(Schema::hasTable($t))->toBeTrue("tabel {$t}");
    }
    expect(Schema::hasColumns('checklist_submissions', ['captured_at_server', 'photo_ref', 'gps_lat', 'gps_lng']))->toBeTrue();
});

it('menolak CRUD tanpa master_data.edit (403)', function () {
    $ops = tap(User::factory()->create())->assignRole(Permissions::ROLE_OPS);
    $this->actingAs($ops)->get(route('admin.checklists.index'))->assertForbidden();
    $this->actingAs($ops)->post(route('admin.checklists.store'), [])->assertForbidden();
});

it('admin definisikan template + item per outlet/shift', function () {
    Outlet::factory()->create(['id_outlet' => 120]);

    $this->actingAs(admin())->post(route('admin.checklists.store'), [
        'name' => 'Shift Pagi', 'schedule' => 'shift', 'id_outlet' => 120, 'active' => 1,
    ])->assertRedirect();

    $t = ChecklistTemplate::first();
    expect($t->schedule)->toBe('shift')->and($t->id_outlet)->toBe(120);

    $this->actingAs(admin())->post(route('admin.checklists.items.store', $t), [
        'label' => 'Cek mesin', 'requires_photo' => 1, 'order' => 1,
    ])->assertRedirect();
    expect($t->items()->first()->requires_photo)->toBeTrue();
});

it('tolak schedule tak valid', function () {
    $this->actingAs(admin())->post(route('admin.checklists.store'), ['name' => 'X', 'schedule' => 'weekly'])
        ->assertSessionHasErrors('schedule');
});

it('hapus template & item', function () {
    $t = ChecklistTemplate::factory()->create();
    $it = ChecklistItem::factory()->create(['template_id' => $t->id]);

    $this->actingAs(admin())->delete(route('admin.checklists.items.destroy', $it))->assertRedirect();
    expect(ChecklistItem::count())->toBe(0);

    $this->actingAs(admin())->delete(route('admin.checklists.destroy', $t))->assertRedirect();
    expect(ChecklistTemplate::count())->toBe(0);
});

it('seeder default: template grup + 4 item (3 wajib foto), idempoten', function () {
    $this->seed(\Database\Seeders\DefaultChecklistSeeder::class);
    $this->seed(\Database\Seeders\DefaultChecklistSeeder::class);

    $t = ChecklistTemplate::whereNull('id_outlet')->first();
    expect($t)->not->toBeNull()
        ->and($t->items)->toHaveCount(4)
        ->and($t->items->where('requires_photo', true)->count())->toBe(3);
    expect(ChecklistTemplate::count())->toBe(1); // idempoten
});

it('ChecklistRun ter-scope per outlet (OPS-1003)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    $t = ChecklistTemplate::factory()->create();
    ChecklistRun::create(['id_outlet' => 120, 'template_id' => $t->id, 'run_date' => '2026-06-18', 'status' => 'open']);
    ChecklistRun::create(['id_outlet' => 121, 'template_id' => $t->id, 'run_date' => '2026-06-18', 'status' => 'open']);

    $staff = User::factory()->create();
    $staff->outlets()->attach(120);

    expect(ChecklistRun::query()->visibleTo($staff)->pluck('id_outlet')->all())->toBe([120]);
});

it('run idempoten per (outlet, template, tanggal)', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    $t = ChecklistTemplate::factory()->create();
    ChecklistRun::create(['id_outlet' => 120, 'template_id' => $t->id, 'run_date' => '2026-06-18', 'status' => 'open']);

    expect(fn () => ChecklistRun::create(['id_outlet' => 120, 'template_id' => $t->id, 'run_date' => '2026-06-18', 'status' => 'open']))
        ->toThrow(\Illuminate\Database\QueryException::class); // unique constraint
});
