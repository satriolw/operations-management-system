<?php

namespace App\Support\Observability;

use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Support\Facades\Log;

/**
 * Titik tunggal alert pipeline ke ops (OPS-702). Log terstruktur (channel oms) + event
 * OpsAlertRaised (disambung ke Slack/email/WA-ops kelak). Konteks disanitasi (tanpa secret/PII).
 */
final class Alerter
{
    /** @param array<string,mixed> $context */
    public static function raise(string $code, array $context = [], string $level = 'error'): void
    {
        $clean = JobTelemetry::sanitize($context);

        Log::channel('oms')->{$level}('alert.'.$code, $clean);
        Metrics::increment('alerts');

        event(new OpsAlertRaised($code, $clean, $level));
    }
}
