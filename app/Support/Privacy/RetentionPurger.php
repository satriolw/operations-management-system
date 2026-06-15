<?php

namespace App\Support\Privacy;

use App\Models\ReportRun;
use App\Models\SignalEvent;

/**
 * Membersihkan payload MENTAH melewati ambang umur (OPS-705, System Design §3.6).
 * Baris + angka turunan + referensi NEVIRA tetap (LBE-ready); hanya payload mentah dinolkan.
 */
final class RetentionPurger
{
    /** Nolkan payload_text + image_path report_runs yang lebih tua dari $days. */
    public function purgeReportPayloads(int $days): int
    {
        $cutoff = now()->subDays($days);

        return ReportRun::query()
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('payload_text')->orWhereNotNull('image_path'))
            ->update(['payload_text' => null, 'image_path' => null]);
    }

    /** Kosongkan payload_json signal_events yang lebih tua dari $days. */
    public function purgeSignalPayloads(int $days): int
    {
        $cutoff = now()->subDays($days);

        return SignalEvent::query()
            ->where('detected_at', '<', $cutoff)
            ->whereNotNull('payload_json')
            ->update(['payload_json' => null]);
    }
}
