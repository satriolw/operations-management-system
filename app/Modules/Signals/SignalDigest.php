<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Support\Observability\Alerter;

/**
 * Digest sinyal LOW (OPS-1002): kumpulkan sinyal severity rendah yang masih OPEN → satu ringkasan
 * (harian/mingguan), bukan notifikasi per-kejadian. High dikecualikan (sudah real-time).
 */
final class SignalDigest
{
    /** @return array{total:int,by_type:array<string,int>} */
    public function build(): array
    {
        $low = SignalEvent::query()->where('severity', 'low')->where('status', 'OPEN')->get();
        $byType = $low->groupBy('type')->map->count()->all();

        if ($low->isNotEmpty()) {
            Alerter::raise('signals.digest', ['total' => $low->count(), 'by_type' => $byType], 'info');
        }

        return ['total' => $low->count(), 'by_type' => $byType];
    }
}
