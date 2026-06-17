<?php

use App\Models\DocumentAttachment;
use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    Storage::fake(config('finance.attachment_disk'));
});

function aDoc(array $over = []): FinancialDocument
{
    static $n = 0;
    $n++;

    return FinancialDocument::factory()->create(array_merge([
        'doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'scope' => 'OUTLET',
        'doc_number' => 'A-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'status' => 'SUBMITTED',
    ], $over));
}

function staffUser(string $role, ?int $outlet = null): User
{
    $u = tap(User::factory()->create())->assignRole($role);
    if ($outlet !== null) {
        $u->outlets()->attach($outlet);
    }

    return $u;
}

it('payload PII/rekening TER-ENKRIPSI at rest (raw DB ≠ plaintext, model dekripsi)', function () {
    $doc = aDoc([
        'doc_type' => 'REFUND', 'nevira_transaction_number' => 'INV/1',
        'payload_json' => ['customer_name' => 'Budi', 'customer_account' => '123-456-789'],
    ]);

    $raw = DB::table('financial_documents')->where('id', $doc->id)->value('payload_json');
    expect($raw)->not->toContain('123-456-789')   // tak tersimpan plaintext
        ->and($raw)->not->toContain('Budi');
    // model membaca balik (dekripsi transparan)
    expect($doc->fresh()->payload_json['customer_account'])->toBe('123-456-789');
});

it('upload lampiran → disk PRIVAT (bukan publik), baris tercatat', function () {
    $hs = staffUser(Permissions::ROLE_HEAD_STORE, 120);
    $doc = aDoc(['id_outlet' => 120]);

    $this->actingAs($hs)->post(route('finance.documents.attachments.store', $doc), [
        'file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'), 'kind' => 'receipt',
    ])->assertRedirect();

    $att = DocumentAttachment::where('document_id', $doc->id)->first();
    expect($att)->not->toBeNull()->and($att->kind)->toBe('receipt');
    Storage::disk(config('finance.attachment_disk'))->assertExists($att->file_ref);
    expect($att->file_ref)->not->toStartWith('public/'); // bukan disk publik
});

it('unduh lampiran: pemilik akses OK; outlet lain 403', function () {
    $hs = staffUser(Permissions::ROLE_HEAD_STORE, 120);
    $doc = aDoc(['id_outlet' => 120]);
    $this->actingAs($hs)->post(route('finance.documents.attachments.store', $doc), [
        'file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
    ]);
    $att = DocumentAttachment::where('document_id', $doc->id)->first();

    $this->actingAs($hs)->get(route('finance.documents.attachments.download', [$doc, $att]))->assertOk();

    $amOther = staffUser(Permissions::ROLE_AREA_MANAGER, 121); // outlet lain
    $this->actingAs($amOther)->get(route('finance.documents.attachments.download', [$doc, $att]))->assertForbidden();
});

it('dokumen FINAL immutable → tolak lampiran baru', function () {
    $hs = staffUser(Permissions::ROLE_HEAD_STORE, 120);
    $doc = aDoc(['id_outlet' => 120, 'status' => 'FINAL', 'finalized_at' => now()]);

    $this->actingAs($hs)->post(route('finance.documents.attachments.store', $doc), [
        'file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'),
    ])->assertForbidden();
});

it('retensi: purge hapus lampiran melewati masa simpan, simpan yang baru', function () {
    config(['finance.attachment_retention_days' => 30]);
    $hs = staffUser(Permissions::ROLE_HEAD_STORE, 120);
    $doc = aDoc(['id_outlet' => 120]);

    $old = $doc->attachments()->create(['file_ref' => 'finance/attachments/old.pdf', 'kind' => 'receipt']);
    Storage::disk(config('finance.attachment_disk'))->put('finance/attachments/old.pdf', 'x');
    $old->forceFill(['created_at' => now()->subDays(40)])->save();

    $new = $doc->attachments()->create(['file_ref' => 'finance/attachments/new.pdf', 'kind' => 'receipt']);
    Storage::disk(config('finance.attachment_disk'))->put('finance/attachments/new.pdf', 'y');

    $this->artisan('oms:purge-finance-attachments')->assertSuccessful();

    expect(DocumentAttachment::find($old->id))->toBeNull()
        ->and(DocumentAttachment::find($new->id))->not->toBeNull();
    Storage::disk(config('finance.attachment_disk'))->assertMissing('finance/attachments/old.pdf');
});
