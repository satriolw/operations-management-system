<?php

namespace App\Modules\Discipline;

use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\Outlet;
use App\Modules\Reporting\OutletCalendar;
use Illuminate\Support\Collection;

/**
 * Penjadwalan run harian checklist (M3-03, System Design §4). Untuk tiap outlet aktif (buka) ×
 * template berlaku (grup id_outlet null + khusus outlet, active, schedule daily) → buat checklist_runs.
 * IDEMPOTEN (unique outlet+template+tanggal via firstOrCreate). Hari tutup/libur → skip (OPS-106).
 */
final class ChecklistScheduler
{
    public function __construct(private readonly OutletCalendar $calendar) {}

    /** @return Collection<int,ChecklistRun> run yang dibuat/ada untuk tanggal tsb */
    public function createDailyRuns(string $date): Collection
    {
        $created = collect();
        $outlets = Outlet::query()->where('active', true)->get();

        foreach ($outlets as $outlet) {
            if ($this->calendar->isClosed((int) $outlet->id_outlet, $date)) {
                continue; // tutup/libur → tak ada checklist
            }

            $templates = ChecklistTemplate::query()
                ->where('active', true)
                ->where('schedule', 'daily')
                ->where(fn ($q) => $q->whereNull('id_outlet')->orWhere('id_outlet', $outlet->id_outlet))
                ->get();

            foreach ($templates as $template) {
                $created->push(ChecklistRun::firstOrCreate(
                    ['id_outlet' => $outlet->id_outlet, 'template_id' => $template->id, 'run_date' => $date],
                    ['status' => ChecklistRun::STATUS_OPEN],
                ));
            }
        }

        return $created;
    }
}
