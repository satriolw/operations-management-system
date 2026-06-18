<?php

namespace Database\Seeders;

use App\Models\ComplianceScore;
use App\Models\DocumentApproval;
use App\Models\FinancialDocument;
use App\Models\LeaderboardSnapshot;
use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\SignalEvent;
use App\Models\User;
use App\Support\Time\Wib;
use Illuminate\Database\Seeder;

/**
 * Data DEMO untuk lihat UI berisi tanpa NEVIRA live (sinyal/laporan/dokumen/checklist/leaderboard).
 * BUKAN data produksi — hanya local/development; di production di-skip. Idempoten (updateOrCreate
 * / firstOrCreate). Data nyata produksi datang dari poller terjadwal (consume NEVIRA), bukan ini.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return; // production → skip (bukan data palsu permanen)
        }

        $outlets = Outlet::query()->where('active', true)->orderBy('id_outlet')->take(3)->get();
        if ($outlets->isEmpty()) {
            return; // tak ada outlet → tak ada konteks demo
        }
        $today = Wib::normalize(now())->toDateString();
        $period = Wib::normalize(now())->format('Y-m');
        $requester = User::query()->first();

        $this->signals($outlets, $today);
        $this->reports($outlets, $today);
        $this->documents($outlets->first(), $requester);
        $this->checklistsAndLeaderboard($outlets, $today, $period);

    }

    private function signals($outlets, string $today): void
    {
        $o = $outlets->first()->id_outlet;
        $rows = [
            ['type' => 'SILENT_OUTLET', 'severity' => 'high', 'payload' => ['checkpoint_hour' => 14, 'realized' => 0, 'date' => $today]],
            ['type' => 'LATE_ORDER', 'severity' => 'high', 'ref' => 'INV/DEMO/9728', 'payload' => ['tier' => 'major', 'overdue_minutes' => 180, 'status_terakhir' => 'WASHING', 'time_in_status_minutes' => 200]],
            ['type' => 'OVERLOAD', 'severity' => 'low', 'payload' => ['tier' => 'warning', 'utilization_pct' => 88.0]],
            ['type' => 'PROMO_LEAKAGE', 'severity' => 'low', 'payload' => ['review_required' => true, 'total_discount' => 620000, 'discount_pct' => 17.5]],
        ];
        foreach ($rows as $r) {
            SignalEvent::firstOrCreate(
                ['id_outlet' => $o, 'type' => $r['type'], 'detected_at' => Wib::parse($today)->setTime(14, 0), 'ref_transaction_number' => $r['ref'] ?? null],
                ['severity' => $r['severity'], 'status' => 'OPEN', 'payload_json' => $r['payload']],
            );
        }
    }

    private function reports($outlets, string $today): void
    {
        foreach ($outlets as $i => $o) {
            ReportRun::updateOrCreate(
                ['id_outlet' => $o->id_outlet, 'report_date' => $today],
                [
                    'status' => $i === 0 ? 'delivered' : 'generated', // 1 terkirim, sisanya menunggu
                    'total_sales' => 10138108 - $i * 500000,
                    'realized' => 9897108 - $i * 500000,
                    'receivable' => 241000,
                    'txn_count' => 93 - $i * 7,
                    'payload_text' => "Laporan harian {$o->name} (demo).",
                ],
            );
        }
    }

    private function documents(Outlet $outlet, ?User $requester): void
    {
        if ($requester === null) {
            return;
        }
        $pr = FinancialDocument::firstOrCreate(
            ['doc_number' => '260618-LW15/PR/OPS/001'],
            [
                'doc_type' => 'PAYMENT_REQUEST', 'brand' => 'LW', 'id_outlet' => $outlet->id_outlet, 'scope' => 'OUTLET',
                'requester_user_id' => $requester->id, 'title' => 'Beli detergen (demo)', 'amount' => 250000,
                'amount_band' => 'LOW', 'cost_center' => 'OPS', 'status' => 'SUBMITTED', 'current_level' => 1,
            ],
        );
        if ($pr->lines()->count() === 0) {
            $pr->lines()->create(['description' => 'Detergen', 'qty' => 2, 'unit_price' => 100000, 'amount' => 200000, 'sort_order' => 0]);
            $pr->lines()->create(['description' => 'Pewangi', 'qty' => 1, 'unit_price' => 50000, 'amount' => 50000, 'sort_order' => 1]);
        }

        $final = FinancialDocument::firstOrCreate(
            ['doc_number' => '260618-LW15/RF/OPS/001'],
            [
                'doc_type' => 'REFUND', 'brand' => 'LW', 'id_outlet' => $outlet->id_outlet, 'scope' => 'OUTLET',
                'requester_user_id' => $requester->id, 'title' => 'Berita Acara Refund (demo)', 'amount' => 75000,
                'amount_band' => 'LOW', 'status' => 'FINAL', 'finalized_at' => now(),
                'nevira_transaction_number' => 'INV/121/1779504406359/1',
                'payload_json' => ['customer_name' => '(demo)', 'reason' => 'Salah cuci'],
            ],
        );
        if ($final->approvals()->count() === 0) {
            DocumentApproval::create(['document_id' => $final->id, 'level' => 1, 'approver_user_id' => $requester->id, 'approver_role' => 'area_manager', 'action' => 'APPROVED', 'acted_at' => now()]);
            DocumentApproval::create(['document_id' => $final->id, 'level' => 2, 'approver_user_id' => $requester->id, 'approver_role' => 'operations_manager', 'action' => 'APPROVED', 'acted_at' => now()]);
        }
    }

    private function checklistsAndLeaderboard($outlets, string $today, string $period): void
    {
        foreach ($outlets as $rank => $o) {
            ComplianceScore::updateOrCreate(
                ['id_outlet' => $o->id_outlet, 'period' => $period],
                ['score' => 90 - $rank * 8, 'runs_count' => 20, 'on_time_items' => 70 - $rank * 5, 'total_items' => 80],
            );
            LeaderboardSnapshot::updateOrCreate(
                ['period' => $period, 'id_outlet' => $o->id_outlet],
                ['raw_score' => 95 - $rank * 10, 'score' => 92 - $rank * 9, 'rank' => $rank + 1, 'metric_breakdown_json' => ['growth' => 100 - $rank * 20, 'compliance' => 90 - $rank * 8]],
            );
        }
    }
}
