<?php

namespace App\Modules\Revenue\DTO;

use App\Modules\Ingestion\DTO\TransactionDTO;

/**
 * Satu koreksi Penyesuaian Revenue (OPS-401): VOID/REFUND disetujui hari ini atas nota hari lampau.
 * Referensi NEVIRA (transaction_number) + nilai turunan; tanpa PII customer. Semua tanggal WIB.
 */
final class Correction
{
    public function __construct(
        public readonly string $transactionNumber,
        public readonly string $type,        // VOID|REFUND
        public readonly int $amount,         // restate = grand_total
        public readonly ?string $reason,     // void_notes/refund_notes (operasional)
        public readonly string $notaDate,    // tgl nota (Y-m-d WIB) — dasar restate
        public readonly string $approvedDate, // tgl disetujui (Y-m-d WIB)
        public readonly ?int $idCashier = null,
    ) {}

    public static function fromTransaction(TransactionDTO $t): self
    {
        return new self(
            transactionNumber: $t->transactionNumber,
            type: strtoupper((string) $t->status),
            amount: $t->grandTotal,
            reason: $t->reason,
            notaDate: (string) $t->notaDate(),
            approvedDate: (string) $t->approvedDate(),
            idCashier: $t->idCashier,
        );
    }
}
