<?php

use App\Models\ChecklistItem;
use App\Models\ChecklistRun;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Discipline\Contracts\Watermarker;
use App\Modules\Discipline\CaptureTokenService;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    Storage::fake(config('discipline.photo_disk'));

    // Fake watermarker: hindari dependensi GD di CI (kembalikan byte apa adanya).
    $this->app->bind(Watermarker::class, fn () => new class implements Watermarker
    {
        public function stamp(string $imageBytes, array $context): string
        {
            return $imageBytes;
        }
    });

    $this->template = ChecklistTemplate::factory()->create(['id_outlet' => 120]);
    $this->photoItem = ChecklistItem::factory()->create(['template_id' => $this->template->id, 'requires_photo' => true]);
    $this->noteItem = ChecklistItem::factory()->create(['template_id' => $this->template->id, 'requires_photo' => false, 'label' => 'Stok']);
    $this->run = ChecklistRun::create(['id_outlet' => 120, 'template_id' => $this->template->id, 'run_date' => '2026-06-18', 'status' => 'open']);
});

function crew(int $outlet = 120): User
{
    $u = tap(User::factory()->create())->assignRole(Permissions::ROLE_HEAD_STORE);
    $u->outlets()->attach($outlet);

    return $u;
}

function fakePhoto(): UploadedFile
{
    return UploadedFile::fake()->create('photo.jpg', 200, 'image/jpeg');
}

it('ANTI-PALSU: foto tanpa capture token (galeri) DITOLAK', function () {
    $u = crew();
    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), [
        'photo' => fakePhoto(), // tanpa capture_token → seolah dari galeri
    ])->assertStatus(422);

    expect(ChecklistSubmission::count())->toBe(0);
});

it('ANTI-PALSU: capture token palsu/forged DITOLAK', function () {
    $u = crew();
    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), [
        'photo' => fakePhoto(), 'capture_token' => 'ngaco.deadbeef',
    ])->assertStatus(422);
    expect(ChecklistSubmission::count())->toBe(0);
});

it('capture token sah (kamera in-app) → submission, photo_ref + captured_at_server (server)', function () {
    $u = crew();
    $token = $this->actingAs($u)->postJson(route('discipline.capture-token', [$this->run, $this->photoItem]))
        ->assertOk()->json('capture_token');

    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), [
        'photo' => fakePhoto(), 'capture_token' => $token,
    ])->assertStatus(201);

    $sub = ChecklistSubmission::first();
    expect($sub->photo_ref)->not->toBeNull()
        ->and($sub->captured_at_server)->not->toBeNull(); // di-stempel server
    Storage::disk(config('discipline.photo_disk'))->assertExists($sub->photo_ref);
});

it('capture token SEKALI pakai (reuse ditolak)', function () {
    $u = crew();
    $svc = app(CaptureTokenService::class);
    $token = $svc->issue($this->run, $this->photoItem, $u);

    expect($svc->verify($token, $this->run, $this->photoItem, $u))->toBeTrue()
        ->and($svc->verify($token, $this->run, $this->photoItem, $u))->toBeFalse(); // sudah dipakai
});

it('capture token kedaluwarsa ditolak', function () {
    config(['discipline.capture_token_ttl' => -10]); // exp di masa lalu
    $u = crew();
    $svc = app(CaptureTokenService::class);
    $token = $svc->issue($this->run, $this->photoItem, $u);

    expect($svc->verify($token, $this->run, $this->photoItem, $u))->toBeFalse();
});

it('item wajib foto tanpa foto → ditolak', function () {
    $u = crew();
    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), ['note' => 'lupa foto'])
        ->assertStatus(422);
});

it('item tanpa foto: submit catatan saja → OK (tanpa token)', function () {
    $u = crew();
    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->noteItem]), ['note' => 'stok aman'])
        ->assertStatus(201);
    expect(ChecklistSubmission::where('item_id', $this->noteItem->id)->first()->note)->toBe('stok aman');
});

it('scoping: crew outlet lain → 403 (token & submit)', function () {
    $other = crew(121);
    $this->actingAs($other)->postJson(route('discipline.capture-token', [$this->run, $this->photoItem]))->assertForbidden();
    $this->actingAs($other)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), ['note' => 'x'])->assertForbidden();
});

it('unduh foto ter-scope: pemilik OK, outlet lain 403', function () {
    $u = crew();
    $token = app(CaptureTokenService::class)->issue($this->run, $this->photoItem, $u);
    $this->actingAs($u)->postJson(route('discipline.submit', [$this->run, $this->photoItem]), [
        'photo' => fakePhoto(), 'capture_token' => $token,
    ])->assertStatus(201);
    $sub = ChecklistSubmission::first();

    $this->actingAs($u)->get(route('discipline.photo', [$this->run, $sub]))->assertOk();
    $this->actingAs(crew(121))->get(route('discipline.photo', [$this->run, $sub]))->assertForbidden();
});

it('retensi: purge hapus foto melewati masa simpan, kosongkan photo_ref', function () {
    config(['discipline.photo_retention_days' => 30]);
    $disk = Storage::disk(config('discipline.photo_disk'));
    $u = crew();

    $old = ChecklistSubmission::create([
        'run_id' => $this->run->id, 'item_id' => $this->photoItem->id, 'crew_user_id' => $u->id,
        'photo_ref' => 'discipline/photos/old.jpg', 'captured_at_server' => now()->subDays(40),
    ]);
    $disk->put('discipline/photos/old.jpg', 'x');

    $this->artisan('oms:purge-checklist-photos')->assertSuccessful();

    expect($old->fresh()->photo_ref)->toBeNull();
    $disk->assertMissing('discipline/photos/old.jpg');
});

it('GD watermarker menempel watermark (byte berubah)', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD tak tersedia.');
    }
    $src = (string) UploadedFile::fake()->image('x.jpg', 200, 200)->get();
    $out = (new \App\Modules\Discipline\GdWatermarker())->stamp($src, ['timestamp' => 't', 'outlet' => 'o', 'item' => 'i']);
    expect(strlen($out))->toBeGreaterThan(0)->and($out)->not->toBe($src);
});
