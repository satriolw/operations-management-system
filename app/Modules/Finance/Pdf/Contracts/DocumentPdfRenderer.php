<?php

namespace App\Modules\Finance\Pdf\Contracts;

use App\Models\FinancialDocument;

/**
 * Render dokumen keuangan → PDF (M2-05). Di balik interface agar implementasi (Browsershot/
 * headless, atau lib lain) dapat ditukar tanpa menyentuh pemanggil. Kebijakan FINAL/preview =
 * DocumentExport. Mengembalikan path PDF.
 */
interface DocumentPdfRenderer
{
    public function toPdf(FinancialDocument $doc, string $outputPath, bool $preview = false): string;
}
