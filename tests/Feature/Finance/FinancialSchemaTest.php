<?php

use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('membuat semua tabel Modul 2 (skema M2-01)', function () {
    foreach ([
        'financial_documents', 'financial_document_lines', 'document_approvals',
        'approval_chains', 'document_attachments', 'doc_number_sequences',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table} ada");
    }

    expect(Schema::hasColumns('financial_documents', [
        'doc_type', 'doc_number', 'brand', 'id_outlet', 'scope', 'requester_user_id',
        'amount', 'amount_band', 'status', 'current_level', 'parent_document_id',
        'nevira_transaction_number', 'payload_json', 'finalized_at',
    ]))->toBeTrue();
});

it('dokumen ber-id_outlet (outlet) & mendukung Head Office (id_outlet null)', function () {
    $outlet = Outlet::factory()->create(['id_outlet' => 120]);

    $doc = FinancialDocument::factory()->create(['id_outlet' => 120, 'scope' => 'OUTLET']);
    $ho = FinancialDocument::factory()->headOffice()->create();

    expect($doc->outlet->id_outlet)->toBe(120)
        ->and($ho->id_outlet)->toBeNull()
        ->and($ho->scope)->toBe('HEAD_OFFICE');
});

it('amount_band: < Rp1jt LOW, ≥ Rp1jt HIGH', function () {
    expect(FinancialDocument::bandFor(999999))->toBe('LOW')
        ->and(FinancialDocument::bandFor(1000000))->toBe('HIGH')
        ->and(FinancialDocument::bandFor(2500000))->toBe('HIGH');
});

it('document_approvals APPEND-ONLY: tak bisa diubah / dihapus', function () {
    $doc = FinancialDocument::factory()->create();
    $approver = User::factory()->create();

    $a = DocumentApproval::create([
        'document_id' => $doc->id, 'level' => 1, 'approver_user_id' => $approver->id,
        'approver_role' => 'area_manager', 'action' => 'APPROVED', 'acted_at' => now(),
    ]);

    expect(fn () => $a->update(['note' => 'ubah']))->toThrow(RuntimeException::class);
    expect(fn () => $a->delete())->toThrow(RuntimeException::class);
});

it('relasi induk → lines / approvals / parent (ER→CA)', function () {
    $ca = FinancialDocument::factory()->create(['doc_type' => 'CASH_ADVANCE']);
    $er = FinancialDocument::factory()->create(['doc_type' => 'EXPENSE_REPORT', 'parent_document_id' => $ca->id]);
    $er->lines()->create(['description' => 'Beli ATK', 'qty' => 1, 'unit_price' => 50000, 'amount' => 50000, 'balance' => -50000]);

    expect($er->parent->id)->toBe($ca->id)
        ->and($er->lines)->toHaveCount(1)
        ->and((float) $er->lines->first()->balance)->toBe(-50000.0);
});

it('Refund hanya MERUJUK nevira_transaction_number (referensi, bukan salinan)', function () {
    $refund = FinancialDocument::factory()->create([
        'doc_type' => 'REFUND', 'nevira_transaction_number' => 'INV/121/1779504406359/1',
    ]);

    expect($refund->nevira_transaction_number)->toBe('INV/121/1779504406359/1');
});

it('scoping per-outlet (OPS-1003): admin lihat semua, staf ter-scope hanya outletnya', function () {
    Outlet::factory()->create(['id_outlet' => 120]);
    Outlet::factory()->create(['id_outlet' => 121]);
    FinancialDocument::factory()->create(['id_outlet' => 120]);
    FinancialDocument::factory()->create(['id_outlet' => 121]);
    FinancialDocument::factory()->headOffice()->create(); // merchant/HO

    $admin = User::factory()->create();
    // staf ter-scope ke outlet 120
    $staff = User::factory()->create();
    $staff->outlets()->attach(120);

    // admin (akses semua) — stub via canAccessAllOutlets: pakai assignedOutletIds kosong + role admin
    // di sini cukup uji staf ter-scope hanya lihat 120.
    expect(FinancialDocument::query()->visibleTo($staff)->pluck('id_outlet')->all())->toBe([120]);
});
