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
 * Anomali pembayaran/kembalian → PAYMENT_ANOMALY (OPS-1403, Epic N, §3.17). Per-nota:
 *  - change_amount > 0 pada metode non-tunai (mis. QRIS 9718 change 87.000),
 *  - Σ payments.amount ≠ grand_total tanpa split (1 baris),
 *  - payment_proof null pada metode wajib bukti.
 *
 * ⚠️ VERIFICATION-GATED: arti change_amount QRIS/DEPOSIT & legitimasi multi-baris (split) belum
 * dikonfirmasi → review_required (flag, bukan tuduhan; digest). Minim-PII. reviewer ≠ subjek.
 */
final class PaymentAnomalyDetector
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
        $cashless = array_map('strtoupper', (array) config('transaction_audit.cashless_methods', []));
        $proofReq = array_map('strtoupper', (array) config('transaction_audit.proof_required_methods', []));
        $reviewMode = (bool) config('transaction_audit.review_mode', true);

        $raised = collect();
        foreach ($this->source->dailyTransactions($idOutlet, new DateRange($day, $day)) as $row) {
            $t = AuditTransaction::fromRow($row);
            [$reasons, $anomalyAmount] = $this->evaluate($t, $cashless, $proofReq);
            if ($reasons === []) {
                continue;
            }
            // Flag bila nominal anomali > ambang ATAU ada bukti hilang (nominal 0 tapi penting).
            if ($anomalyAmount <= $cfg->payment_anomaly_min_amount && ! in_array('proof_missing', $reasons, true)) {
                continue;
            }

            $raised->push($this->raise($idOutlet, $day, $t, $reasons, $anomalyAmount, $cfg, $reviewMode));
        }

        return $raised;
    }

    /** @return array{0:array<int,string>,1:float} [reasons, anomalyAmount] */
    private function evaluate(AuditTransaction $t, array $cashless, array $proofReq): array
    {
        $payments = $t->payments();
        $reasons = [];
        $amount = 0.0;

        foreach ($payments as $p) {
            $method = strtoupper((string) $p['method']);
            if (in_array($method, $cashless, true) && $p['change_amount'] > 0) {
                $reasons[] = 'cashless_change';
                $amount = max($amount, $p['change_amount']);
            }
            if (in_array($method, $proofReq, true) && ($p['payment_proof'] === null || $p['payment_proof'] === '')) {
                $reasons[] = 'proof_missing';
            }
        }

        // Net dibayar = amount − change (kembalian bukan pembayaran). Mismatch hanya bila 1 baris (tanpa split).
        $net = collect($payments)->sum(fn ($p) => $p['amount'] - $p['change_amount']);
        if (count($payments) === 1 && abs($net - $t->grandTotal()) > 0.01) {
            $reasons[] = 'amount_mismatch';
            $amount = max($amount, abs($net - $t->grandTotal()));
        }

        return [array_values(array_unique($reasons)), $amount];
    }

    private function raise(int $idOutlet, string $day, AuditTransaction $t, array $reasons, float $amount, TransactionAuditConfig $cfg, bool $reviewMode): SignalEvent
    {
        // "Real-time bila besar" hanya bila verifikasi sudah selesai; selama review_mode → digest.
        $severity = (! $reviewMode && $amount >= $cfg->payment_anomaly_min_amount) ? 'high' : 'low';

        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'PAYMENT_ANOMALY', 'ref_transaction_number' => $t->transactionNumber(), 'detected_at' => Wib::parse($day)->startOfDay()],
            [
                'severity' => $severity, 'status' => 'OPEN', 'id_cashier' => $t->idCashier(),
                'payload_json' => [
                    'review_required' => $reviewMode,
                    'note' => 'Perlu ditinjau (bukan tuduhan): semantik change_amount/split belum dikonfirmasi NEVIRA.',
                    'reasons' => $reasons,
                    'anomaly_amount' => round($amount, 2),
                    'grand_total' => $t->grandTotal(),
                    'id_customer' => $t->idCustomer(), // referensi, bukan PII
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
