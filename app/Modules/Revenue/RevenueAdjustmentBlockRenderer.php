<?php

namespace App\Modules\Revenue;

use App\Modules\Revenue\DTO\Correction;
use App\Modules\Revenue\DTO\RestateSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Render blok opsional "Penyesuaian Revenue" (OPS-403, template PRD §8.4): nota, jenis, nominal,
 * alasan, total, revenue lama→baru. Tidak tampil bila tak ada koreksi (return null). Rupiah & tanggal id-ID.
 * Output = string untuk token {{penyesuaian_revenue}} (dipakai ReportComposer/ReportMessageBuilder).
 */
final class RevenueAdjustmentBlockRenderer
{
    /**
     * @param  Collection<int, Correction>  $corrections
     */
    public function render(Collection $corrections, RestateSummary $summary): ?string
    {
        if ($summary->isEmpty() || $corrections->isEmpty()) {
            return null; // blok absen total
        }

        $lines = ['PENYESUAIAN REVENUE'];

        foreach ($corrections as $c) {
            $reason = $c->reason ? ' — "'.$c->reason.'"' : '';
            $lines[] = sprintf('- %s (%s) %s %s%s',
                $c->transactionNumber, $this->dateShort($c->notaDate),
                ucfirst(strtolower($c->type)), $this->rp($c->amount), $reason);
        }

        $lines[] = 'Total koreksi: -'.$this->rp($summary->totalCorrection);

        foreach ($summary->byDate as $date => $d) {
            if ($d['old'] !== null && $d['new'] !== null) {
                $lines[] = 'Revenue '.$this->dateShort($date).': '.$this->rp($d['old']).' → '.$this->rp($d['new']);
            }
        }

        return implode("\n", $lines);
    }

    private function rp(int $v): string
    {
        return 'Rp'.number_format($v, 0, ',', '.');
    }

    private function dateShort(string $date): string
    {
        return CarbonImmutable::parse($date, 'Asia/Jakarta')->locale('id')->translatedFormat('d M');
    }
}
