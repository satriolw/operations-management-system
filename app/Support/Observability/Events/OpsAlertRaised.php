<?php

namespace App\Support\Observability\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Alert operasional diangkat (OPS-702). Hook untuk channel notifikasi (Slack/email/WA-ops)
 * yang disambungkan kemudian tanpa mengubah pemanggil.
 */
class OpsAlertRaised
{
    use Dispatchable;

    /** @param array<string,mixed> $context */
    public function __construct(
        public readonly string $code,
        public readonly array $context = [],
        public readonly string $level = 'error',
    ) {}
}
