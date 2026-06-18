<?php

namespace App\Modules\Signals;

use App\Models\SignalEvent;
use App\Models\TransactionAuditConfig;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\AuditTransaction;
use App\Modules\Ingestion\DTO\DateRange;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Audit kebocoran promo → PROMO_LEAKAGE (OPS-1402, Epic N, System Design §3.17). Agregasi nilai
 * diskon promos[] per outlet/kasir/jenis; flag bila > cap harian ATAU > % omzet. Reuse poller
 * (dailyTransactions). Promo whitelist dikecualikan.
 *
 * ⚠️ VERIFICATION-GATED: kelengkapan promos[] & whitelist belum dikonfirmasi → mode "perlu ditinjau"
 * (review_required=true, severity rendah/digest, BUKAN tuduhan, tanpa auto-aksi). reviewer ≠ subjek
 * (id_cashier). Minim-PII: hanya id_cashier/transaction_number/id_customer (referensi).
 */
final class PromoLeakageDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly SignalRouter $router,
    ) {}

    public function detect(int $idOutlet, CarbonInterface|string|null $date = null): ?SignalEvent
    {
        $day = ($date === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $date)))->toDateString();
        $whitelist = (array) config('transaction_audit.promo_whitelist', []);
        $cfg = TransactionAuditConfig::forOutlet($idOutlet);

        $txns = $this->source->dailyTransactions($idOutlet, new DateRange($day, $day))
            ->map(fn ($row) => AuditTransaction::fromRow($row));

        $omzet = $txns->sum(fn (AuditTransaction $t) => $t->grandTotal());
        $totalDiscount = $txns->sum(fn (AuditTransaction $t) => $t->promoTotal($whitelist));
        if ($totalDiscount <= 0) {
            return null;
        }

        $pct = $omzet > 0 ? round($totalDiscount / $omzet * 100, 2) : null;
        $overCap = $totalDiscount > $cfg->promo_leak_daily_cap;
        $overPct = $pct !== null && $pct > $cfg->promo_leak_pct;
        if (! $overCap && ! $overPct) {
            return null;
        }

        return $this->raise($idOutlet, $day, $txns, $totalDiscount, $omzet, $pct, $cfg, $whitelist, $overCap, $overPct);
    }

    private function raise(int $idOutlet, string $day, Collection $txns, float $totalDiscount, float $omzet, ?float $pct, TransactionAuditConfig $cfg, array $whitelist, bool $overCap, bool $overPct): SignalEvent
    {
        // Dimensi (tanpa PII): per kasir + per jenis promo.
        $byCashier = [];
        $byPromo = [];
        foreach ($txns as $t) {
            foreach ($t->promos() as $p) {
                if (in_array($p['name'], $whitelist, true) || $p['amount'] <= 0) {
                    continue;
                }
                $byCashier[(int) $t->idCashier()] = ($byCashier[(int) $t->idCashier()] ?? 0) + $p['amount'];
                $byPromo[$p['name']] = ($byPromo[$p['name']] ?? 0) + $p['amount'];
            }
        }
        arsort($byCashier);
        arsort($byPromo);

        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'PROMO_LEAKAGE', 'detected_at' => Wib::parse($day)->startOfDay()],
            [
                'severity' => 'low', // digest (OPS-1002) — verification-gated, bukan real-time
                'status' => 'OPEN',
                'payload_json' => [
                    'review_required' => (bool) config('transaction_audit.review_mode', true),
                    'note' => 'Perlu ditinjau (bukan tuduhan): kelengkapan promos[]/whitelist belum dikonfirmasi NEVIRA.',
                    'date' => $day,
                    'total_discount' => round($totalDiscount, 2),
                    'omzet' => round($omzet, 2),
                    'discount_pct' => $pct,
                    'daily_cap' => $cfg->promo_leak_daily_cap,
                    'threshold_pct' => $cfg->promo_leak_pct,
                    'triggered_by' => array_values(array_filter(['cap' => $overCap, 'pct' => $overPct], fn ($v) => $v)) ? array_keys(array_filter(['cap' => $overCap, 'pct' => $overPct])) : [],
                    'by_cashier' => $byCashier,   // id_cashier → nominal (referensi, bukan PII)
                    'by_promo_type' => $byPromo,
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal); // low → digest
        }

        return $signal;
    }
}
