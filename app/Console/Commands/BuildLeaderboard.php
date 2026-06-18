<?php

namespace App\Console\Commands;

use App\Modules\Discipline\LeaderboardBuilder;
use App\Modules\Discipline\RevenueByOutlet;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * M3-06 · bangun leaderboard periode (revenue NEVIRA + metrik ternormalisasi + rata-rata bergerak).
 * Self-monitored. Default periode = bulan berjalan.
 */
class BuildLeaderboard extends Command
{
    protected $signature = 'oms:build-leaderboard {--period=}';

    protected $description = 'Bangun snapshot leaderboard ternormalisasi per periode (M3-06).';

    public function handle(RevenueByOutlet $revenue, LeaderboardBuilder $builder): int
    {
        $period = $this->option('period') ?: Wib::normalize(now())->format('Y-m');
        $prior = CarbonImmutable::parse($period.'-01', 'Asia/Jakarta')->subMonth()->format('Y-m');

        try {
            $rows = $builder->build($period, $revenue->forPeriod($period), $revenue->forPeriod($prior));
            $this->info("Leaderboard {$period}: {$rows->count()} outlet.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            Alerter::raise('leaderboard.build_failed', ['period' => $period, 'message' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
