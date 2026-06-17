<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Pdf\BrowsershotPdfRenderer;
use App\Modules\Finance\Pdf\Contracts\DocumentPdfRenderer;
use Illuminate\Support\ServiceProvider;

/**
 * Binding domain Finance (Modul 2). DocumentPdfRenderer → Browsershot (M2-05); ganti adapter
 * tanpa menyentuh pemanggil.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentPdfRenderer::class, BrowsershotPdfRenderer::class);
    }
}
