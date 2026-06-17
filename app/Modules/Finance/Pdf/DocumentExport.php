<?php

namespace App\Modules\Finance\Pdf;

use App\Models\FinancialDocument;
use App\Modules\Finance\Exceptions\ApprovalException;

/**
 * Kebijakan ekspor PDF (M2-05): hanya dokumen FINAL yang diekspor "bersih"; non-FINAL hanya boleh
 * sebagai PREVIEW ber-watermark "DRAFT". Menghasilkan HTML siap-render (murni, testable).
 */
final class DocumentExport
{
    public function __construct(private readonly DocumentHtml $html) {}

    public function html(FinancialDocument $doc, bool $preview = false): string
    {
        if (! $doc->isFinal() && ! $preview) {
            throw new ApprovalException('Hanya dokumen FINAL yang dapat diekspor; gunakan preview untuk DRAFT.');
        }

        // FINAL → bersih; selain itu (preview) → watermark DRAFT.
        return $this->html->build($doc, watermark: ! $doc->isFinal());
    }
}
