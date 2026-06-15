<?php

namespace App\Console\Commands;

use App\Support\Observability\Metrics;
use Illuminate\Console\Command;

/**
 * Visibilitas metrik dasar (OPS-701). Tanpa Redis/Horizon pun metrik tetap terbaca di sini.
 */
class ShowMetrics extends Command
{
    protected $signature = 'oms:metrics {--json : Keluarkan sebagai JSON}';

    protected $description = 'Tampilkan metrik OPS (laporan, panggilan NEVIRA, kegagalan re-auth, latensi).';

    public function handle(): int
    {
        $counters = Metrics::all();
        $latency = [
            'reporting.generate' => Metrics::latency('reporting.generate'),
        ];

        if ($this->option('json')) {
            $this->line(json_encode(['counters' => $counters, 'latency' => $latency], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Counter:');
        $this->table(['metrik', 'nilai'], collect($counters)->map(fn ($v, $k) => [$k, $v])->values());

        $this->info('Latensi job (ms):');
        $this->table(
            ['job', 'count', 'avg', 'last'],
            collect($latency)->map(fn ($l, $k) => [$k, $l['count'], $l['avg'], $l['last']])->values(),
        );

        return self::SUCCESS;
    }
}
