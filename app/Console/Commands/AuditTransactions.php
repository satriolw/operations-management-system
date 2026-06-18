<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Modules\Signals\DepositExpiryDetector;
use App\Modules\Signals\OffPriceSaleDetector;
use App\Modules\Signals\PaymentAnomalyDetector;
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

    protected $description = 'Audit anomali transaksi (Epic N): promo/payment/off-price/deposit (perlu ditinjau).';

    public function handle(PromoLeakageDetector $promo, PaymentAnomalyDetector $payment, OffPriceSaleDetector $offprice, DepositExpiryDetector $deposit): int
    {
        $date = $this->option('date') ?: Wib::normalize(now())->subDay()->toDateString(); // default kemarin (hari penuh)
        $count = 0;

        foreach (Outlet::query()->where('active', true)->pluck('id_outlet') as $idOutlet) {
            $id = (int) $idOutlet;
            try {
                $count += $promo->detect($id, $date) ? 1 : 0;
                $count += $payment->detect($id, $date)->count();
                $count += $offprice->detect($id, $date)->count();
                $count += $deposit->detect($id, $date)->count();
            } catch (Throwable $e) {
                Alerter::raise('audit.failed', ['id_outlet' => $id, 'message' => $e->getMessage()]);
            }
        }

        $this->info("Audit transaksi {$date}: {$count} sinyal (promo/payment/off-price/deposit).");

        return self::SUCCESS;
    }
}
