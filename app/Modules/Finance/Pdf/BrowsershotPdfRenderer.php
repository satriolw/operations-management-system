<?php

namespace App\Modules\Finance\Pdf;

use App\Models\FinancialDocument;
use App\Modules\Finance\Pdf\Contracts\DocumentPdfRenderer;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Render dokumen → PDF via headless Chromium (Browsershot) (M2-05). Butuh Node + Chromium.
 * HTML & kebijakan FINAL/preview dari DocumentExport (murni, testable); hanya langkah biner di sini.
 */
final class BrowsershotPdfRenderer implements DocumentPdfRenderer
{
    public function __construct(private readonly DocumentExport $export) {}

    public function toPdf(FinancialDocument $doc, string $outputPath, bool $preview = false): string
    {
        $html = $this->export->html($doc, $preview); // melempar bila non-FINAL tanpa preview

        try {
            Browsershot::html($html)
                ->format('A4')
                ->margins(12, 12, 12, 12)
                ->showBackground()
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal render PDF dokumen (Chromium?): '.$e->getMessage(), 0, $e);
        }

        return $outputPath;
    }
}
