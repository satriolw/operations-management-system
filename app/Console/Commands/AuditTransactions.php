<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Modules\Signals\PromoLeakageDetector;
use App\Support\Observability\Alerter;
use App\Support\Time\Wib;
use Illuminate\Console\Command;
use Throwable;

/**
 * Epic N (OPS-1402..) · audit transaksi harian per outlet. Verification-gated ("perlu ditinjau").
 * Saat ini: PROMO_LEAKAGE (paling pasti). Sinyal lain (payment/off-price/deposit) menyusul.
 */
class AuditTransactions extends Command
{
    protected $signature = 'oms:audit-transactions {--date=}';

    protected $description = 'Audit anomali transaksi (Epic N) — promo dulu (perlu ditinjau).';

    public function handle(PromoLeakageDetector $promo): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->subDay()->toDateString(); // default kemarin (hari penuh)
        $count = 0;

        foreach (Outlet::query()->where('active', true)->pluck('id_outlet') as $idOutlet) {
            try {
                if ($promo->detect((int) $idOutlet, $date)) {
                    $count++;
                }
            } catch (Throwable $e) {
                Alerter::raise('audit.promo_failed', ['id_outlet' => $idOutlet, 'message' => $e->getMessage()]);
            }
        }

        $this->info("Audit transaksi {$date}: {$count} sinyal promo.");

        return self::SUCCESS;
    }
}
