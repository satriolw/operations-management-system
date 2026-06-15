<?php

namespace App\Modules\Ingestion\Contracts;

use App\Modules\Ingestion\DTO\DashboardDTO;
use App\Modules\Ingestion\DTO\DateRange;
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
}
