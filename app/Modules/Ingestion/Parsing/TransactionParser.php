<?php

namespace App\Modules\Ingestion\Parsing;

use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Support\Time\Wib;
use Illuminate\Support\Collection;

/**
 * Memetakan record transaksi NEVIRA mentah → TransactionDTO (OPS-103).
 *
 * - Tanggal diambil dari field TINGKAT-TRANSAKSI lalu dinormalkan WIB (Wib). Field nested
 *   "services" (UTC) sengaja diabaikan untuk logika tanggal (Risiko R1).
 * - Tahan field null: void_notes null saat REFUND, refund_notes null saat VOID, dst.
 */
final class TransactionParser
{
    public function fromArray(array $row): TransactionDTO
    {
        // Alasan: VOID pakai void_notes, REFUND pakai refund_notes; keduanya mutually-null.
        $reason = $row['void_notes'] ?? $row['refund_notes'] ?? null;

        return new TransactionDTO(
            transactionNumber: (string) ($row['transaction_number'] ?? ''),
            idTransaction: isset($row['id_transaction']) ? (int) $row['id_transaction'] : null,
            status: $row['status'] ?? null,
            grandTotal: (int) ($row['grand_total'] ?? 0),
            createdAt: Wib::parseNullable($row['created_at'] ?? null),
            approvedAt: Wib::parseNullable($row['approve_refund_void_date'] ?? null),
            requestedAt: Wib::parseNullable($row['request_refund_void_date'] ?? null),
            reason: $reason !== null ? (string) $reason : null,
            refundVoidBy: isset($row['refund_void_by']) ? (int) $row['refund_void_by'] : null,
            refundVoidApprovedBy: isset($row['refund_void_approved_by']) ? (int) $row['refund_void_approved_by'] : null,
            idCashier: isset($row['id_cashier']) ? (int) $row['id_cashier'] : null,
            paymentStatus: $row['payment_status'] ?? null,
            progressPercentage: (int) ($row['progress_percentage'] ?? 0),
            idOutlet: isset($row['id_outlet']) ? (int) $row['id_outlet'] : null,
            idRole: data_get($row, 'cashier.id_role') !== null ? (int) data_get($row, 'cashier.id_role') : null,
        );
    }

    /**
     * @param  iterable<array<string, mixed>>  $rows
     * @return Collection<int, TransactionDTO>
     */
    public function collection(iterable $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => $this->fromArray($row))->values();
    }
}
