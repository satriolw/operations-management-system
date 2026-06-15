<?php

namespace App\Support\Observability;

use App\Support\Privacy\PiiPolicy;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus observability per job (OPS-701, System Design §4.3).
 * Mencatat log TERSTRUKTUR (job, id_outlet, report_date, durasi, hasil) + latensi metrik,
 * untuk sukses maupun gagal. Context disanitasi: TIDAK pernah me-log secret/token/PII.
 */
final class JobTelemetry
{
    private const CHANNEL = 'oms';

    /** Substring kunci context yang menandakan secret → dibuang sebelum log. */
    private const SECRET_KEY_TOKENS = [
        'token', 'password', 'secret', 'authorization',
        'credential', 'api_key', 'apikey', 'bearer',
    ];

    /**
     * Jalankan $work dengan telemetry. Mengembalikan hasil $work; melempar ulang exception
     * setelah mencatat kegagalan.
     *
     * @template T
     * @param  Closure():T  $work
     * @return T
     */
    public static function run(string $job, array $context, Closure $work): mixed
    {
        $ctx = self::sanitize($context) + ['job' => $job];
        $start = microtime(true);

        try {
            $result = $work();
            $ms = self::elapsedMs($start);
            Metrics::observeLatency($job, $ms);
            Log::channel(self::CHANNEL)->info('job.success', $ctx + [
                'result' => 'success',
                'duration_ms' => $ms,
            ]);

            return $result;
        } catch (Throwable $e) {
            $ms = self::elapsedMs($start);
            Metrics::observeLatency($job, $ms);
            Log::channel(self::CHANNEL)->error('job.failure', $ctx + [
                'result' => 'failure',
                'duration_ms' => $ms,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Buang kunci secret & PII customer dari context (rekursif).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitize(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (self::isSecretKey((string) $key) || PiiPolicy::isForbiddenColumn((string) $key)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $clean;
    }

    private static function isSecretKey(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::SECRET_KEY_TOKENS as $token) {
            if (str_contains($k, $token)) {
                return true;
            }
        }

        return false;
    }

    private static function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
