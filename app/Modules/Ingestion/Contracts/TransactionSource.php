<?php

namespace App\Modules\Ingestion\Contracts;

use App\Modules\Ingestion\DTO\DashboardDTO;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\MerchantBalanceDTO;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Anti-corruption layer ke NEVIRA (System Design §3.1/§3.2). SEMUA domain
 * (Reporting/Revenue/Signals) bergantung pada kontrak ini — bukan klien HTTP konkret —
 * agar sumber dapat ditukar (REST → webhook/replica) tanpa menyentuh domain.
 *
 * Akses NEVIRA HANYA via REST API. Tidak ada direct DB.
 */
interface TransactionSource
{
    /** Metrik dashboard harian satu outlet pada satu tanggal (WIB). */
    public function dailyDashboard(int $outletId, CarbonInterface|string $date): DashboardDTO;

    /**
     * Transaksi VOID + REFUND (is_void_refund=true) pada rentang tanggal.
     * Semua halaman dikumpulkan. Mengembalikan record mentah (parsing = OPS-103).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function voidRefunds(int $outletId, DateRange $range): Collection;

    /**
     * Transaksi UNPAID (payment_status=UNPAID) pada rentang tanggal — untuk Piutang.
     * Semua halaman dikumpulkan.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function unpaid(int $outletId, DateRange $range): Collection;

    /**
     * Backlog order BERJALAN (belum selesai) satu outlet — sumber beban/overload (OPS-1103)
     * & SLA nota terlambat (OPS-1301). Semua halaman dikumpulkan; record mentah (normalisasi
     * di domain). Field SLA penting: progress_percentage, estimated_completion_date,
     * completion_date, status, updated_at, id_rack, order_type.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function activeOrders(int $outletId): Collection;

    /**
     * Saldo deposit tingkat-merchant (Epic L, OPS-1201) — saldo_total + breakdown.
     * SATU request: TIDAK menarik history (paginated, ~1.989 halaman). Runway/burn = delta
     * antar-snapshot (OPS-1202). $range membatasi jendela breakdown count per aksi.
     */
    public function merchantBalance(DateRange $range): MerchantBalanceDTO;
}
