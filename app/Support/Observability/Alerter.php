<?php

namespace App\Support\Observability;

use App\Support\Observability\Events\OpsAlertRaised;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Dedup alert (OPS-503): angkat hanya SEKALI per $key per hari (reset tengah malam).
     * Cegah alert berulang utk kondisi sama. Mengembalikan true bila benar-benar diangkat.
     *
     * @param  array<string,mixed>  $context
     */
    public static function raiseOnce(string $key, string $code, array $context = [], string $level = 'error'): bool
    {
        $cacheKey = 'oms:alert-once:'.$key;
        $store = Cache::store(config('oms.metrics_cache_store'));

        if ($store->has($cacheKey)) {
            return false; // sudah dialarm hari ini
        }

        $store->put($cacheKey, true, now()->endOfDay());
        self::raise($code, $context, $level);

        return true;
    }
}
