<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\Contracts\DashboardImageRenderer;
use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Render kartu dashboard → PNG via headless Chromium (Browsershot) untuk lampiran WhatsApp (OPS-204).
 * Butuh Node + Chromium di server. Bila gagal → RuntimeException; pemanggil (OPS-206) toleran:
 * laporan teks tetap terkirim tanpa gambar (R2).
 */
final class BrowsershotRenderer implements DashboardImageRenderer
{
    // Lebar kartu 375px @2x → tajam untuk media WhatsApp.
    private const WIDTH = 375;
    private const DEVICE_SCALE = 2;

    public function __construct(private readonly DashboardCardHtml $html) {}

    public function render(DailyMetrics $metrics, RevenueSplit $split, string $date, array $context, string $outputPath): string
    {
        $html = $this->html->build($metrics, $split, $date, $context);

        try {
            Browsershot::html($html)
                ->windowSize(self::WIDTH, 10)   // tinggi auto via fullPage
                ->deviceScaleFactor(self::DEVICE_SCALE)
                ->fullPage()
                ->setScreenshotType('png')
                ->save($outputPath);
        } catch (Throwable $e) {
            throw new RuntimeException('Gagal render gambar dashboard (Chromium?): '.$e->getMessage(), 0, $e);
        }

        return $outputPath;
    }
}
