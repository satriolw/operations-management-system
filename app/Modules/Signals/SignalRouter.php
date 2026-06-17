<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Support\Observability\Alerter;

/**
 * Severity tiering (OPS-1002): sinyal HIGH → alert real-time ke ops; sinyal LOW → TIDAK ada
 * notifikasi per-kejadian (masuk digest, lihat SignalDigest). Cegah alert fatigue (75% = input error rutin).
 */
final class SignalRouter
{
    public function notify(SignalEvent $signal): void
    {
        if ($signal->severity !== 'high') {
            return; // low → digest, bukan real-time
        }

        Alerter::raise('signal.'.strtolower((string) $signal->type), [
            'id_outlet' => $signal->id_outlet,
            'ref' => $signal->ref_transaction_number,
            'severity' => 'high',
        ]);
    }
}
