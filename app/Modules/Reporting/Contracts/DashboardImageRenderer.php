<?php

namespace App\Modules\Reporting\Contracts;

use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;

/**
 * Render kartu dashboard harian → gambar PNG untuk lampiran WhatsApp (OPS-204).
 * Di balik interface agar implementasi (Browsershot/headless, atau fallback) dapat ditukar
 * tanpa menyentuh pipeline laporan (R2). Pipeline harus toleran bila render gagal (teks tetap kirim).
 */
interface DashboardImageRenderer
{
    /**
     * @param  array{nama_outlet?:string,nama_investor?:string}  $context
     * @return string  path file PNG yang dihasilkan
     */
    public function render(DailyMetrics $metrics, RevenueSplit $split, string $date, array $context, string $outputPath): string;
}
