<?php

namespace App\Modules\Ingestion\DTO;

use Carbon\CarbonImmutable;

/**
 * Model internal satu transaksi NEVIRA (OPS-103). Transient — BUKAN tabel/persist
 * (aturan emas #2). Hanya field yang dipakai laporan & sinyal; tanpa PII customer.
 *
 * Semua tanggal sudah dinormalkan WIB dari field TINGKAT-TRANSAKSI (created_at,
 * approve/request_refund_void_date). Timestamp nested "services" (UTC) TIDAK dipetakan
 * ke sini agar tak pernah dipakai untuk logika tanggal (Risiko R1).
 */
final class TransactionDTO
{
    public function __construct(
        public readonly string $transactionNumber,
        public readonly ?int $idTransaction,
        public readonly ?string $status,            // VOID|REFUND|COMPLETED|...
        public readonly int $grandTotal,            // rupiah
        public readonly ?CarbonImmutable $createdAt,    // tgl nota (WIB)
        public readonly ?CarbonImmutable $approvedAt,   // approve_refund_void_date (WIB)
        public readonly ?CarbonImmutable $requestedAt,  // request_refund_void_date (WIB)
        public readonly ?string $reason,           // void_notes / refund_notes
        public readonly ?int $refundVoidBy,        // pemohon (id_user)
        public readonly ?int $refundVoidApprovedBy, // penyetuju (id_user)
        public readonly ?int $idCashier,           // pembuat nota (aktor NEVIRA) — atribusi KPI
        public readonly ?string $paymentStatus,    // PAID|UNPAID
        public readonly int $progressPercentage,
        public readonly ?int $idOutlet,
        public readonly ?int $idRole = null,        // id_role aktor (proxy penyetuju saat self-approval; OPS-601)
    ) {}

    public function isVoid(): bool
    {
        return strtoupper((string) $this->status) === 'VOID';
    }

    public function isRefund(): bool
    {
        return strtoupper((string) $this->status) === 'REFUND';
    }

    public function isUnpaid(): bool
    {
        return strtoupper((string) $this->paymentStatus) === 'UNPAID';
    }

    /** Pemohon == penyetuju (kandidat self-approval; pelanggaran/tidak dinilai OPS-601). */
    public function isSelfApproval(): bool
    {
        return $this->refundVoidBy !== null && $this->refundVoidBy === $this->refundVoidApprovedBy;
    }

    /** Tanggal nota (Y-m-d, WIB) — dasar restate Penyesuaian Revenue. */
    public function notaDate(): ?string
    {
        return $this->createdAt?->format('Y-m-d');
    }

    /** Tanggal disetujui (Y-m-d, WIB). */
    public function approvedDate(): ?string
    {
        return $this->approvedAt?->format('Y-m-d');
    }
}
