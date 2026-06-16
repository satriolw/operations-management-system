<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use Illuminate\Support\ServiceProvider;

/**
 * Binding domain Reporting. DashboardImageRenderer → Browsershot (OPS-204); ganti adapter
 * (mis. fallback no-image) tanpa menyentuh pipeline.
 */
class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DashboardImageRenderer::class, BrowsershotRenderer::class);
    }
}
