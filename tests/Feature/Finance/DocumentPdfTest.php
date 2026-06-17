<?php

use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\User;
use App\Modules\Finance\Exceptions\ApprovalException;
use App\Modules\Finance\Pdf\BrowsershotPdfRenderer;
use App\Modules\Finance\Pdf\DocumentExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => \App\Models\Outlet::factory()->create(['id_outlet' => 120]));

function finalDoc(array $over = []): FinancialDocument
{
    static $n = 0;
    $n++;

    return FinancialDocument::factory()->create(array_merge([
        'doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => 120, 'scope' => 'OUTLET',
        'doc_number' => '260610-LW06/PR/OPS/'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        'title' => 'Beli sabun', 'amount' => 250000,
        'amount_band' => 'LOW', 'status' => 'FINAL', 'finalized_at' => now(),
    ], $over));
}

it('HTML PR memuat doc_number, judul, line items, total, label jenis', function () {
    $doc = finalDoc(['doc_number' => '260610-LW06/PR/OPS/001']);
    $doc->lines()->create(['description' => 'Detergen', 'merk_type' => 'Rinso', 'qty' => 2, 'unit_price' => 100000, 'amount' => 200000, 'sort_order' => 0]);
    $doc->lines()->create(['description' => 'Pewangi', 'qty' => 1, 'unit_price' => 50000, 'amount' => 50000, 'sort_order' => 1]);

    $html = app(DocumentExport::class)->html($doc);

    expect($html)->toContain('260610-LW06/PR/OPS/001')
        ->and($html)->toContain('Payment Request')
        ->and($html)->toContain('Detergen')
        ->and($html)->toContain('Rp250.000');
});

it('kelima jenis ter-render (label + non-kosong)', function () {
    $labels = [
        'PAYMENT_REQUEST' => 'Payment Request', 'REIMBURSE' => 'Reimbursement', 'CASH_ADVANCE' => 'Cash Advance',
        'EXPENSE_REPORT' => 'Expense Report', 'REFUND' => 'Berita Acara Refund',
    ];
    foreach ($labels as $type => $label) {
        $html = app(DocumentExport::class)->html(finalDoc(['doc_type' => $type]));
        expect($html)->toContain($label);
    }
});

it('blok approval mencerminkan document_approvals (nama, status, level, waktu)', function () {
    $doc = finalDoc();
    $am = User::factory()->create(['name' => 'Andi AM']);
    $om = User::factory()->create(['name' => 'Oka OM']);
    DocumentApproval::create(['document_id' => $doc->id, 'level' => 1, 'approver_user_id' => $am->id, 'approver_role' => 'area_manager', 'action' => 'APPROVED', 'acted_at' => now()]);
    DocumentApproval::create(['document_id' => $doc->id, 'level' => 2, 'approver_user_id' => $om->id, 'approver_role' => 'operations_manager', 'action' => 'APPROVED', 'acted_at' => now()]);

    $html = app(DocumentExport::class)->html($doc);
    expect($html)->toContain('Andi AM')->and($html)->toContain('Oka OM')
        ->and($html)->toContain('area_manager')->and($html)->toContain('L1');
});

it('FINAL → tanpa watermark; preview DRAFT → ber-watermark', function () {
    $final = finalDoc();
    $draft = finalDoc(['status' => 'DRAFT', 'finalized_at' => null]);

    expect(app(DocumentExport::class)->html($final))->not->toContain('class="watermark"');
    expect(app(DocumentExport::class)->html($draft, preview: true))->toContain('class="watermark"');
});

it('kebijakan ekspor: non-FINAL tanpa preview → ditolak; FINAL → boleh', function () {
    $draft = finalDoc(['status' => 'SUBMITTED', 'finalized_at' => null]);

    expect(fn () => app(DocumentExport::class)->html($draft))->toThrow(ApprovalException::class);
    expect(app(DocumentExport::class)->html(finalDoc()))->toBeString();
});

it('Refund: DATA TRANSAKSI + No. Nota NEVIRA (referensi) + rekening customer', function () {
    $doc = finalDoc([
        'doc_type' => 'REFUND', 'amount' => 75000, 'nevira_transaction_number' => 'INV/121/1779504406359/1',
        'payload_json' => ['customer_name' => 'Budi', 'customer_phone' => '0812', 'customer_account' => '123-456', 'reason' => 'Salah cuci'],
    ]);

    $html = app(DocumentExport::class)->html($doc);
    expect($html)->toContain('Data Transaksi')
        ->and($html)->toContain('INV/121/1779504406359/1')
        ->and($html)->toContain('Budi')->and($html)->toContain('123-456');
});

it('Expense Report: parent CA + kolom Balance', function () {
    $ca = finalDoc(['doc_type' => 'CASH_ADVANCE', 'doc_number' => '260610-LW06/CA/OPS/001', 'amount' => 1000000]);
    $er = finalDoc(['doc_type' => 'EXPENSE_REPORT', 'doc_number' => '260610-LW06/ER/OPS/001', 'parent_document_id' => $ca->id, 'amount' => 1100000]);
    $er->lines()->create(['description' => 'Konsumsi', 'qty' => 1, 'unit_price' => 1100000, 'amount' => 1100000, 'balance' => -100000, 'sort_order' => 0]);

    $html = app(DocumentExport::class)->html($er);
    expect($html)->toContain('260610-LW06/CA/OPS/001') // parent
        ->and($html)->toContain('Balance')
        ->and($html)->toContain('Rp-100.000');
});

it('blok FAT-P statis hadir (pasca-FINAL, bukan level approval)', function () {
    expect(app(DocumentExport::class)->html(finalDoc()))->toContain('FAT-P Division');
});

it('Browsershot toPdf menghasilkan file PDF', function () {
    $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
    if ($node === '' || env('OMS_TEST_BROWSERSHOT') !== '1') {
        $this->markTestSkipped('Lewati: Node/Chromium tak tersedia (set OMS_TEST_BROWSERSHOT=1 bila ada).');
    }

    $out = storage_path('app/test-doc.pdf');
    app(BrowsershotPdfRenderer::class)->toPdf(finalDoc(), $out);
    expect(file_exists($out))->toBeTrue();
    @unlink($out);
});
