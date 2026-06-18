<?php

namespace App\Console\Commands;

use App\Models\ChecklistSubmission;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * M3-02 · retensi foto checklist (data sensitif crew: wajah/lokasi). Hapus file foto + kosongkan
 * photo_ref untuk submission lebih tua dari discipline.photo_retention_days. Selaras OPS-705/M2-06.
 */
class PurgeChecklistPhotos extends Command
{
    protected $signature = 'oms:purge-checklist-photos';

    protected $description = 'Hapus foto checklist melewati masa retensi (M3-02).';

    public function handle(): int
    {
        $days = (int) config('discipline.photo_retention_days', 365);
        $cutoff = Wib::normalize(now())->subDays($days);
        $disk = Storage::disk(config('discipline.photo_disk', 'local'));

        $count = 0;
        ChecklistSubmission::query()
            ->whereNotNull('photo_ref')
            ->where('captured_at_server', '<', $cutoff)
            ->each(function (ChecklistSubmission $s) use ($disk, &$count) {
                $disk->delete($s->photo_ref);
                $s->forceFill(['photo_ref' => null])->save();
                $count++;
            });

        $this->info("Retensi foto checklist: {$count} dihapus (lebih tua dari {$days} hari).");

        return self::SUCCESS;
    }
}
