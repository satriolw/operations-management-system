<?php

namespace App\Console\Commands;

use App\Modules\Reporting\BackfillService;
use Illuminate\Console\Command;

/**
 * OPS-703 · jalankan ulang laporan tanggal lampau (idempoten). --dry-run = preview tanpa persist/kirim.
 */
class BackfillReport extends Command
{
    protected $signature = 'oms:report-backfill {outlet} {date} {--dry-run}';

    protected $description = 'Backfill/replay laporan harian untuk satu (outlet, tanggal) — idempoten (OPS-703).';

    public function handle(BackfillService $service): int
    {
        $r = $service->run((int) $this->argument('outlet'), (string) $this->argument('date'), (bool) $this->option('dry-run'));

        $this->info($r['dry_run']
            ? "DRY-RUN (tidak disimpan):\n".$r['text']
            : ($r['persisted'] ? "Tersimpan report_run #{$r['report_run_id']}." : 'Disuppress (outlet tutup/libur).'));

        return self::SUCCESS;
    }
}
