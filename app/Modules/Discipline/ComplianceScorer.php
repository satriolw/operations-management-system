<?php

namespace App\Modules\Discipline;

use App\Models\ChecklistRun;
use App\Models\ComplianceScore;
use App\Support\Time\Wib;
use Illuminate\Support\Collection;

/**
 * Skor kepatuhan checklist (M3-04, System Design §4). Skor run = % item selesai TEPAT WAKTU
 * (submission ada, + photo bila wajib, captured_at_server ≤ deadline run). Agregat per outlet/periode
 * → compliance_scores (KPI Head Store, query-able LBE).
 */
final class ComplianceScorer
{
    /** Hitung & simpan skor tiap run pada tanggal; lalu agregasi periode (bulan) per outlet. */
    public function scoreDate(string $date): void
    {
        $runs = ChecklistRun::query()->where('run_date', $date)
            ->with(['template.items', 'submissions'])->get();

        foreach ($runs as $run) {
            $run->update(['score' => $this->scoreRun($run)]);
        }

        $period = Wib::parse($date)->format('Y-m');
        $runs->pluck('id_outlet')->unique()->each(fn ($idOutlet) => $this->aggregate((int) $idOutlet, $period));
    }

    /** Skor satu run: % item selesai tepat waktu. */
    public function scoreRun(ChecklistRun $run): float
    {
        [$onTime, $total] = $this->countOnTime($run);

        return $total === 0 ? 0.0 : round($onTime / $total * 100, 2);
    }

    /** Agregasi rata-rata skor run per outlet/periode → persist (idempoten per outlet+periode). */
    public function aggregate(int $idOutlet, string $period): ComplianceScore
    {
        $runs = ChecklistRun::query()
            ->where('id_outlet', $idOutlet)
            ->where('run_date', 'like', $period.'%')
            ->with(['template.items', 'submissions'])->get();

        $onTime = 0;
        $total = 0;
        $scoreSum = 0.0;
        foreach ($runs as $run) {
            [$o, $t] = $this->countOnTime($run);
            $onTime += $o;
            $total += $t;
            $scoreSum += $this->scoreRun($run);
        }

        $avg = $runs->count() === 0 ? 0.0 : round($scoreSum / $runs->count(), 2);

        return ComplianceScore::updateOrCreate(
            ['id_outlet' => $idOutlet, 'period' => $period],
            ['score' => $avg, 'runs_count' => $runs->count(), 'on_time_items' => $onTime, 'total_items' => $total],
        );
    }

    /** @return array{0:int,1:int} [item tepat waktu, total item] */
    private function countOnTime(ChecklistRun $run): array
    {
        $deadline = Wib::parse((string) $run->run_date)->setTime((int) config('discipline.escalation_hour', 20), 0);
        $byItem = $run->submissions->keyBy('item_id');
        $onTime = 0;
        $total = $run->template->items->count();

        foreach ($run->template->items as $item) {
            $sub = $byItem->get($item->id);
            $done = $sub !== null && (! $item->requires_photo || $sub->photo_ref !== null);
            if ($done && $sub->captured_at_server !== null && Wib::normalize($sub->captured_at_server)->lte($deadline)) {
                $onTime++;
            }
        }

        return [$onTime, $total];
    }
}
