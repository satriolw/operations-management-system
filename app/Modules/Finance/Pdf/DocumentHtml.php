<?php

namespace App\Modules\Finance\Pdf;

use App\Models\FinancialDocument;

/**
 * Bangun HTML dokumen keuangan untuk PDF (M2-05). Murni (tanpa headless browser) → testable di CI.
 * Lima jenis via satu template + seksi kondisional; blok approval mencerminkan document_approvals;
 * blok FAT-P statis (pasca-FINAL); watermark "DRAFT" bila bukan FINAL.
 */
final class DocumentHtml
{
    public const LABELS = [
        'PAYMENT_REQUEST' => 'Payment Request',
        'REIMBURSE' => 'Reimbursement',
        'CASH_ADVANCE' => 'Cash Advance',
        'EXPENSE_REPORT' => 'Expense Report',
        'REFUND' => 'Berita Acara Refund',
    ];

    public function build(FinancialDocument $doc, bool $watermark): string
    {
        $doc->loadMissing(['lines' => fn ($q) => $q->orderBy('sort_order'), 'approvals.approver', 'parent']);

        return view('finance.pdf.document', [
            'doc' => $doc,
            'watermark' => $watermark,
            'typeLabel' => self::LABELS[$doc->doc_type] ?? $doc->doc_type,
            'payload' => $doc->payload_json ?? [],
        ])->render();
    }
}
