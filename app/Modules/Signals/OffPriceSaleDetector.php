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
 * Penjualan di bawah price-list → OFF_PRICE_SALE (OPS-1404, Epic N, §3.17). Bandingkan services[].price
 * vs price-list (service_data.price); flag selisih > toleransi. Grup B2B resmi (id_customer_group)
 * DIKECUALIKAN. Per-nota.
 *
 * ⚠️ VERIFICATION-GATED: keberadaan price-list/grup B2B resmi belum dikonfirmasi → review_required
 * (flag, bukan tuduhan; digest). Minim-PII. reviewer ≠ subjek.
 */
final class OffPriceSaleDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly SignalRouter $router,
    ) {}

    /** @return Collection<int,SignalEvent> */
    public function detect(int $idOutlet, CarbonInterface|string|null $date = null): Collection
    {
        $day = ($date === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $date)))->toDateString();
        $cfg = TransactionAuditConfig::forOutlet($idOutlet);
        $b2bGroups = array_map('intval', (array) config('transaction_audit.b2b_customer_groups', []));
        $reviewMode = (bool) config('transaction_audit.review_mode', true);

        $raised = collect();
        foreach ($this->source->dailyTransactions($idOutlet, new DateRange($day, $day)) as $row) {
            $t = AuditTransaction::fromRow($row);
            if (in_array((int) $t->deposit()['id_customer_group'], $b2bGroups, true)) {
                continue; // grup B2B resmi → harga khusus sah
            }

            $offending = [];
            $gap = 0.0;
            foreach ($t->services() as $s) {
                if ($s['list_price'] === null || $s['list_price'] <= 0 || $s['price'] >= $s['list_price']) {
                    continue;
                }
                $pct = round(($s['list_price'] - $s['price']) / $s['list_price'] * 100, 2);
                if ($pct > $cfg->offprice_tolerance_pct) {
                    $offending[] = ['price' => $s['price'], 'list_price' => $s['list_price'], 'gap_pct' => $pct];
                    $gap += $s['list_price'] - $s['price'];
                }
            }
            if ($offending === []) {
                continue;
            }

            $raised->push($this->raise($idOutlet, $day, $t, $offending, $gap, $reviewMode));
        }

        return $raised;
    }

    private function raise(int $idOutlet, string $day, AuditTransaction $t, array $offending, float $gap, bool $reviewMode): SignalEvent
    {
        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'OFF_PRICE_SALE', 'ref_transaction_number' => $t->transactionNumber(), 'detected_at' => Wib::parse($day)->startOfDay()],
            [
                'severity' => 'low', 'status' => 'OPEN', 'id_cashier' => $t->idCashier(),
                'payload_json' => [
                    'review_required' => $reviewMode,
                    'note' => 'Perlu ditinjau (bukan tuduhan): price-list/grup B2B resmi belum dikonfirmasi NEVIRA.',
                    'offending_lines' => $offending,
                    'total_gap' => round($gap, 2),
                    'id_customer_group' => $t->deposit()['id_customer_group'],
                    'date' => $day,
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal);
        }

        return $signal;
    }
}
