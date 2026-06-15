<?php

namespace App\Support\Observability;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Metrik dasar yang dapat dibaca (OPS-701, System Design §4.3). Disimpan di cache
 * (Redis di prod, array saat test) → dibaca command `oms:metrics` / dashboard.
 *
 * Counter: laporan generated/delivered/failed, panggilan NEVIRA, kegagalan re-auth.
 * Latensi job: count + sum (untuk rata-rata) + last.
 */
final class Metrics
{
    public const REPORTS_GENERATED = 'reports_generated';

    public const REPORTS_DELIVERED = 'reports_delivered';

    public const REPORTS_FAILED = 'reports_failed';

    public const NEVIRA_CALLS = 'nevira_calls';

    public const NEVIRA_REAUTH_FAILURES = 'nevira_reauth_failures';

    private const PREFIX = 'oms:metric:';

    /** @var string[] counter yang dilaporkan command oms:metrics */
    public const COUNTERS = [
        self::REPORTS_GENERATED,
        self::REPORTS_DELIVERED,
        self::REPORTS_FAILED,
        self::NEVIRA_CALLS,
        self::NEVIRA_REAUTH_FAILURES,
    ];

    public static function increment(string $name, int $by = 1): void
    {
        $store = self::store();
        $key = self::PREFIX.$name;
        // add() menetapkan 0 bila belum ada (array store increment butuh nilai awal int).
        $store->add($key, 0);
        $store->increment($key, $by);
    }

    public static function get(string $name): int
    {
        return (int) self::store()->get(self::PREFIX.$name, 0);
    }

    /** Catat latensi satu job (ms): count + sum + last. */
    public static function observeLatency(string $job, int $ms): void
    {
        self::increment("latency:{$job}:count", 1);
        self::increment("latency:{$job}:sum", $ms);
        self::store()->put(self::PREFIX."latency:{$job}:last", $ms);
    }

    /** @return array{count:int,sum:int,avg:float,last:int} */
    public static function latency(string $job): array
    {
        $count = self::get("latency:{$job}:count");
        $sum = self::get("latency:{$job}:sum");

        return [
            'count' => $count,
            'sum' => $sum,
            'avg' => $count > 0 ? round($sum / $count, 1) : 0.0,
            'last' => self::get("latency:{$job}:last"),
        ];
    }

    /** @return array<string,int> semua counter untuk visibilitas. */
    public static function all(): array
    {
        $out = [];
        foreach (self::COUNTERS as $c) {
            $out[$c] = self::get($c);
        }

        return $out;
    }

    private static function store(): CacheRepository
    {
        return Cache::store(config('oms.metrics_cache_store'));
    }
}
