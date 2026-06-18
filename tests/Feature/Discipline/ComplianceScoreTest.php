<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use App\Models\ComplianceScore;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Discipline\ComplianceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const SDATE = '2026-06-18';

beforeEach(function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    $this->crew = User::factory()->create();
});

/** Run dgn 2 item (foto + catatan). */
function scoreRun(int $idOutlet = 120, string $date = SDATE): ChecklistRun
{
    $t = ChecklistTemplate::factory()->create(['id_outlet' => $idOutlet]);
    ChecklistItem::factory()->create(['template_id' => $t->id, 'requires_photo' => true, 'label' => 'Foto']);
    ChecklistItem::factory()->create(['template_id' => $t->id, 'requires_photo' => false, 'label' => 'Catatan']);

    return ChecklistRun::create(['id_outlet' => $idOutlet, 'template_id' => $t->id, 'run_date' => $date, 'status' => 'open']);
}

function submit(ChecklistRun $run, string $label, bool $withPhoto, string $at): void
{
    $item = $run->template->items->firstWhere('label', $label);
    ChecklistSubmission::create([
        'run_id' => $run->id, 'item_id' => $item->id, 'crew_user_id' => test()->crew->id,
        'photo_ref' => $withPhoto ? 'p.jpg' : null, 'captured_at_server' => $at,
    ]);
}

it('skor run 100 bila semua item tepat waktu', function () {
    $run = scoreRun();
    submit($run, 'Foto', true, SDATE.' 08:00:00');
    submit($run, 'Catatan', false, SDATE.' 08:05:00');

    expect(app(ComplianceScorer::class)->scoreRun($run))->toEqual(100.0);
});

it('skor run 50 bila separuh selesai', function () {
    $run = scoreRun();
    submit($run, 'Foto', true, SDATE.' 08:00:00'); // 1 dari 2

    expect(app(ComplianceScorer::class)->scoreRun($run))->toEqual(50.0);
});

it('submission TERLAMBAT (setelah deadline) tak dihitung tepat waktu', function () {
    $run = scoreRun();
    submit($run, 'Foto', true, SDATE.' 08:00:00');      // tepat waktu
    submit($run, 'Catatan', false, SDATE.' 21:00:00');  // > deadline 20:00 → late

    expect(app(ComplianceScorer::class)->scoreRun($run))->toEqual(50.0);
});

it('item wajib foto tanpa photo_ref tak dihitung selesai', function () {
    $run = scoreRun();
    submit($run, 'Foto', false, SDATE.' 08:00:00'); // wajib foto tapi tanpa photo_ref
    submit($run, 'Catatan', false, SDATE.' 08:00:00');

    expect(app(ComplianceScorer::class)->scoreRun($run))->toEqual(50.0);
});

it('agregat periode: rata-rata skor run per outlet, tersimpan & idempoten', function () {
    $r1 = scoreRun(120, '2026-06-10');
    submit($r1, 'Foto', true, '2026-06-10 08:00:00');
    submit($r1, 'Catatan', false, '2026-06-10 08:00:00'); // 100

    $r2 = scoreRun(120, '2026-06-11');
    submit($r2, 'Foto', true, '2026-06-11 08:00:00'); // 50

    app(ComplianceScorer::class)->scoreDate('2026-06-10');
    app(ComplianceScorer::class)->scoreDate('2026-06-11');
    app(ComplianceScorer::class)->aggregate(120, '2026-06'); // idempoten

    $cs = ComplianceScore::where(['id_outlet' => 120, 'period' => '2026-06'])->first();
    expect($cs)->not->toBeNull()
        ->and((float) $cs->score)->toEqual(75.0)   // (100+50)/2
        ->and($cs->runs_count)->toBe(2);
    expect(ComplianceScore::count())->toBe(1); // idempoten
});

it('skor kepatuhan ter-scope per outlet (OPS-1003)', function () {
    ComplianceScore::create(['id_outlet' => 120, 'period' => '2026-06', 'score' => 80]);
    ComplianceScore::create(['id_outlet' => 121, 'period' => '2026-06', 'score' => 90]);

    $staff = User::factory()->create();
    $staff->outlets()->attach(120);

    expect(ComplianceScore::query()->visibleTo($staff)->pluck('id_outlet')->all())->toBe([120]);
});

it('command oms:score-checklists menyimpan skor', function () {
    $run = scoreRun(120, SDATE);
    submit($run, 'Foto', true, SDATE.' 08:00:00');
    submit($run, 'Catatan', false, SDATE.' 08:00:00');

    $this->artisan('oms:score-checklists', ['--date' => SDATE])->assertSuccessful();

    expect((float) $run->fresh()->score)->toEqual(100.0)
        ->and(ComplianceScore::where('id_outlet', 120)->where('period', '2026-06')->exists())->toBeTrue();
});
