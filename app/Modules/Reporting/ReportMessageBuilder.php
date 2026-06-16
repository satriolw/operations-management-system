<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use App\Modules\Templating\TemplateRenderer;
use App\Modules\Templating\TemplateResolver;

/**
 * Susun teks pesan laporan harian (OPS-203). Rakit token (metrik + split + outlet/investor/tanggal)
 * lalu render via template aktif (master→override). Hide-zero, Rupiah & tanggal id-ID ditangani
 * TemplateRenderer (OPS-903). Blok Penyesuaian Revenue opsional (dari OPS-401/403) bila ada.
 */
final class ReportMessageBuilder
{
    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array{nama_outlet?:string,nama_investor?:string,penyesuaian_revenue?:?string}  $context
     */
    public function build(int $idOutlet, string $date, DailyMetrics $metrics, RevenueSplit $split, array $context = []): string
    {
        $template = $this->resolver->forOutlet($idOutlet);
        if ($template === null) {
            // Pipeline tak boleh terblokir: seed default seharusnya selalu ada (OPS-901).
            throw new \RuntimeException('Tidak ada template aktif — jalankan DefaultTemplateSeeder (OPS-901).');
        }

        $tokens = array_merge($metrics->toTokens(), $split->toTokens(), [
            'nama_outlet' => $context['nama_outlet'] ?? '',
            'nama_investor' => $context['nama_investor'] ?? '',
            'tanggal' => $date,
            'penyesuaian_revenue' => $context['penyesuaian_revenue'] ?? null,
        ]);

        return $this->renderer->renderText($template, $tokens);
    }
}
