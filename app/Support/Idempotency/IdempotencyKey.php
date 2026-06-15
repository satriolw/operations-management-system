<?php

namespace App\Support\Idempotency;

/**
 * Kunci idempotency kanonik (aturan emas #5). Re-run/replay TIDAK menghasilkan efek ganda.
 *  - report run: per (outlet, report_date)
 *  - delivery:   per (report_run, channel) → "tepat satu channel aktif per target per hari"
 */
final class IdempotencyKey
{
    public static function reportRun(int $idOutlet, string $reportDate): string
    {
        return "report:{$idOutlet}:{$reportDate}";
    }

    public static function delivery(int $reportRunId, string $channel): string
    {
        return "delivery:{$reportRunId}:{$channel}";
    }
}
