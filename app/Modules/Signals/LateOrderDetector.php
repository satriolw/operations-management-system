<?php

namespace App\Modules\Signals;

use App\Models\OutletSlaConfig;
use App\Models\SignalEvent;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\ActiveOrder;
use App\Support\Time\Wib;
use Carbon\CarbonInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Deteksi nota terlambat / macet (Epic M, OPS-1303/1304, System Design §3.16). Reuse activeOrders
 * (OPS-1102) — bukan poller baru. overdue diukur JAM OPERASIONAL (mode business_hours) → tak banjir
 * false-positive nota lintas-tutup. "Proses terkini" = status + time-in-status (BUKAN log tahap).
 * Sinyal LATE_ORDER severity-tiered (OPS-1002), payload TANPA PII pelanggan. Status terminal NEVIRA
 * belum dikonfirmasi → status di luar daftar diperlakukan masih-terbuka (perlu ditinjau).
 */
final class LateOrderDetector
{
    public function __construct(
        private readonly TransactionSource $source,
        private readonly BusinessHoursClock $clock,
        private readonly SignalRouter $router,
    ) {}

    /** @return Collection<int,SignalEvent> */
    public function detect(int $idOutlet, CarbonInterface|string|null $now = null): Collection
    {
        $at = $now === null ? Wib::normalize(now()) : Wib::normalize(Wib::parse((string) $now));
        $cfg = OutletSlaConfig::forOutlet($idOutlet);
        $terminal = array_map('strtoupper', (array) config('late_orders.terminal_statuses', []));
        $excluded = array_map('strtoupper', (array) config('late_orders.excluded_statuses', []));

        $raised = collect();
        foreach ($this->source->activeOrders($idOutlet) as $row) {
            $order = ActiveOrder::fromRow($row);
            if (! $order->isOpen()) {
                continue; // completion_date terisi → selesai
            }
            $st = strtoupper((string) $order->status);
            if (in_array($st, $terminal, true) || in_array($st, $excluded, true)) {
                continue;
            }

            $tier = $this->classify($idOutlet, $order, $cfg, $at);
            if ($tier === null) {
                continue;
            }

            $raised->push($this->raise($idOutlet, $order, $cfg, $at, $tier));
        }

        return $raised;
    }

    /** approaching|minor|major|stuck|null. */
    private function classify(int $idOutlet, ActiveOrder $o, OutletSlaConfig $cfg, CarbonImmutable $at): ?string
    {
        $overdue = $this->overdueMinutes($idOutlet, $o, $cfg, $at);
        if ($overdue !== null && $overdue > $cfg->grace_minutes) {
            return $overdue > $cfg->minor_overdue_minutes ? 'major' : 'minor';
        }

        $tis = $this->timeInStatusMinutes($idOutlet, $o, $cfg, $at);
        if ($tis !== null && $tis > $cfg->stuck_minutes_threshold && (float) $o->progressPercentage < 100) {
            return 'stuck';
        }

        $until = $this->untilDeadlineMinutes($idOutlet, $o, $cfg, $at);
        if ($until !== null && $until > 0 && $until <= $cfg->approaching_lead_minutes) {
            return 'approaching';
        }

        return null;
    }

    /** Menit overdue (est → now). null bila est tak ada / belum lewat. business_hours → jam operasional. */
    private function overdueMinutes(int $idOutlet, ActiveOrder $o, OutletSlaConfig $cfg, CarbonImmutable $at): ?int
    {
        if ($o->estimatedCompletion === null || $at->lte($o->estimatedCompletion)) {
            return $o->estimatedCompletion === null ? null : 0;
        }

        return $cfg->sla_clock_mode === 'wallclock'
            ? (int) round($o->estimatedCompletion->diffInMinutes($at))
            : $this->clock->operationalMinutesBetween($idOutlet, $o->estimatedCompletion, $at);
    }

    /** Menit menuju deadline (now → est). null bila est tak ada / sudah lewat. */
    private function untilDeadlineMinutes(int $idOutlet, ActiveOrder $o, OutletSlaConfig $cfg, CarbonImmutable $at): ?int
    {
        if ($o->estimatedCompletion === null || $o->estimatedCompletion->lte($at)) {
            return null;
        }

        return $cfg->sla_clock_mode === 'wallclock'
            ? (int) round($at->diffInMinutes($o->estimatedCompletion))
            : $this->clock->operationalMinutesBetween($idOutlet, $at, $o->estimatedCompletion);
    }

    /** Time-in-status: jam operasional (business_hours) atau wall-clock; konsisten dgn overdue. */
    private function timeInStatusMinutes(int $idOutlet, ActiveOrder $o, OutletSlaConfig $cfg, CarbonImmutable $at): ?int
    {
        if ($o->updatedAt === null || $at->lte($o->updatedAt)) {
            return $o->updatedAt === null ? null : 0;
        }

        return $cfg->sla_clock_mode === 'wallclock'
            ? (int) round($o->updatedAt->diffInMinutes($at))
            : $this->clock->operationalMinutesBetween($idOutlet, $o->updatedAt, $at);
    }

    private function raise(int $idOutlet, ActiveOrder $o, OutletSlaConfig $cfg, CarbonImmutable $at, string $tier): SignalEvent
    {
        $severity = in_array($tier, ['major', 'stuck'], true) ? 'high' : 'low'; // OPS-1002 tiering
        $overdue = $this->overdueMinutes($idOutlet, $o, $cfg, $at);
        $tis = $this->timeInStatusMinutes($idOutlet, $o, $cfg, $at);

        $signal = SignalEvent::firstOrCreate(
            [
                'id_outlet' => $idOutlet, 'type' => 'LATE_ORDER',
                'ref_transaction_number' => $o->transactionNumber,
                'detected_at' => $at->startOfDay(), // idempoten per nota per hari
            ],
            [
                'severity' => $severity,
                'status' => 'OPEN',
                'id_cashier' => $o->idCashier,
                'payload_json' => [ // metadata SLA — TANPA PII pelanggan (aturan emas #3)
                    'tier' => $tier,
                    'estimated_completion' => $o->estimatedCompletion?->format('Y-m-d H:i'),
                    'overdue_minutes' => $overdue,
                    'status_terakhir' => $o->status,            // "proses terakhir" = status + ...
                    'time_in_status_minutes' => $tis,           // ... sejak kapan (macet)
                    'progress_percentage' => $o->progressPercentage,
                    'id_rack' => $o->idRack,
                    'order_type' => $o->orderType,
                    'clock_mode' => $cfg->sla_clock_mode,
                    'evaluated_at' => $at->format('Y-m-d H:i'),
                ],
            ],
        );

        if ($signal->wasRecentlyCreated) {
            $this->router->notify($signal); // high → real-time; low → digest
        }

        return $signal;
    }
}
