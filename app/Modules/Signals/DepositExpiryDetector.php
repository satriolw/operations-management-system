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
 * Monitor deposit/membership akan kedaluwarsa → DEPOSIT_EXPIRY (OPS-1406, Epic N, §3.17). MONITOR
 * (retensi/proteksi revenue), BUKAN tuduhan. ⚠️ PRIVASI: simpan hanya id_customer (referensi) +
 * nominal + tanggal — TANPA nama/telepon; aksi (hubungi pelanggan) via lookup NEVIRA. Digest, low.
 */
final class DepositExpiryDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly SignalRouter $router,
    ) {}

    /** @return Collection<int,SignalEvent> */
    public function detect(int $idOutlet, CarbonInterface|string|null $date = null): Collection
    {
        $at = $date === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $date));
        $day = $at->toDateString();
        $lead = (int) TransactionAuditConfig::forOutlet($idOutlet)->deposit_expiry_lead_days;

        $raised = collect();
        $seen = [];
        foreach ($this->source->dailyTransactions($idOutlet, new DateRange($day, $day)) as $row) {
            $t = AuditTransaction::fromRow($row);
            $dep = $t->deposit();
            $cust = $t->idCustomer();
            if ($cust === null || in_array($cust, $seen, true) || $dep['active_until'] === null) {
                continue;
            }
            $until = Wib::parse((string) $dep['active_until']);
            $daysUntil = (int) floor($at->copy()->startOfDay()->diffInDays($until->startOfDay(), false));
            if ($daysUntil < 0 || $daysUntil > $lead) {
                continue; // belum mendekati kedaluwarsa
            }
            $seen[] = $cust;

            $raised->push($this->raise($idOutlet, $day, $cust, $dep, $daysUntil));
        }

        return $raised;
    }

    private function raise(int $idOutlet, string $day, int $idCustomer, array $dep, int $daysUntil): SignalEvent
    {
        $signal = SignalEvent::firstOrCreate(
            ['id_outlet' => $idOutlet, 'type' => 'DEPOSIT_EXPIRY', 'ref_transaction_number' => 'CUST/'.$idCustomer, 'detected_at' => Wib::parse($day)->startOfDay()],
            [
                'severity' => 'low', 'status' => 'OPEN',
                'payload_json' => [ // MONITOR, tanpa PII (hanya id_customer referensi)
                    'note' => 'Monitor retensi (bukan tuduhan). Aksi via lookup NEVIRA.',
                    'id_customer' => $idCustomer,
                    'deposit_balance' => $dep['balance'],
                    'active_until' => $dep['active_until'],
                    'days_until_expiry' => $daysUntil,
                    'date' => $day,
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal); // low → digest
        }

        return $signal;
    }
}
