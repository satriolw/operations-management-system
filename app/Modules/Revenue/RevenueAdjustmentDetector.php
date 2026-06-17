<?php

namespace App\Modules\Revenue;

use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Ingestion\Parsing\TransactionParser;
use App\Modules\Revenue\DTO\Correction;
use Illuminate\Support\Collection;

/**
 * Deteksi koreksi cross-day Penyesuaian Revenue (OPS-401, PRD §8.4 / CLAUDE.md).
 *
 * Aturan: VOID + REFUND dengan approve_refund_void_date = HARI INI DAN created_at < HARI INI,
 * jendela lookback ~7 hari (approval bisa telat & batch). Mencakup VOID (unpaid) DAN REFUND (paid)
 * karena total_sales NEVIRA memuat piutang B2B → keduanya me-restate revenue tanggal nota.
 *
 * ⚠️ R1: tanggal diambil dari field TINGKAT-TRANSAKSI yang sudah dinormalkan WIB (TransactionDTO),
 * bukan nested services (UTC). Batas tengah malam aman.
 */
final class RevenueAdjustmentDetector
{
    private const LOOKBACK_DAYS = 7;

    public function __construct(
        private readonly TransactionSource $source,
        private readonly TransactionParser $parser,
    ) {}

    /**
     * @return Collection<int, Correction> koreksi unik (per transaction_number) utk laporan $today
     */
    public function detect(int $idOutlet, string $today): Collection
    {
        $range = DateRange::lookback($today, self::LOOKBACK_DAYS);
        $rows = $this->source->voidRefunds($idOutlet, $range);

        return $this->parser->collection($rows)
            ->filter(fn (TransactionDTO $t) => $this->isCrossDayCorrection($t, $today))
            ->unique(fn (TransactionDTO $t) => $t->transactionNumber) // tidak menghitung ganda
            ->map(fn (TransactionDTO $t) => Correction::fromTransaction($t))
            ->values();
    }

    private function isCrossDayCorrection(TransactionDTO $t, string $today): bool
    {
        if (! $t->isVoid() && ! $t->isRefund()) {
            return false;
        }

        $nota = $t->notaDate();
        $approved = $t->approvedDate();

        // disetujui HARI INI atas nota HARI LAMPAU (cross-day)
        return $approved === $today && $nota !== null && $nota < $today;
    }
}
