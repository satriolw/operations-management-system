<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\DTO\DailyMetrics;
use App\Modules\Reporting\DTO\RevenueSplit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * Bangun HTML kartu dashboard mobile (~375px) dari metrik harian (OPS-204). Deterministik
 * (tanpa now()/random) → snapshot-testable. Hide-zero; Rupiah & tanggal locale id-ID.
 * HTML ini lalu dirender ke PNG oleh DashboardImageRenderer.
 */
final class DashboardCardHtml
{
    public function __construct(private readonly ViewFactory $view) {}

    public function build(DailyMetrics $metrics, RevenueSplit $split, string $date, array $context = []): string
    {
        return $this->view->make('reports.dashboard-card', [
            'outletName' => $context['nama_outlet'] ?? '',
            'investor' => $context['nama_investor'] ?? '',
            'tanggal' => CarbonImmutable::parse($date, 'Asia/Jakarta')->locale('id')->translatedFormat('l, d F Y'),
            'totalRp' => $this->rp($split->totalSales),
            'realizedRp' => $this->rp($split->realized),
            'piutangRp' => $this->rp($split->receivable),
            'hasPiutang' => $split->receivable > 0,
            'txnCount' => $metrics->txnCount,
            'avgTrxRp' => $this->rp($metrics->avgTransaction),
            'avgCustRp' => $this->rp($metrics->avgCustomerSpending),
            'volumes' => $this->volumes($metrics->volumes), // sudah hide-zero
        ])->render();
    }

    private function rp(int $v): string
    {
        return 'Rp'.number_format($v, 0, ',', '.');
    }

    /** @return array<string,int> hanya volume > 0 (hide-zero), label rapi. */
    private function volumes(array $volumes): array
    {
        $labels = ['kg' => 'Kg', 'pcs' => 'Pcs', 'm2' => 'M²', 'pasang' => 'Pasang', 'lembar' => 'Lembar'];
        $out = [];
        foreach ($volumes as $key => $val) {
            if ((int) $val > 0) {
                $out[$labels[$key] ?? ucfirst((string) $key)] = (int) $val;
            }
        }

        return $out;
    }
}
