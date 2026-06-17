<?php

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan approval dokumen keuangan (M2-03). Dilempar engine pada transisi tak valid,
 * approver salah, reviewer = requester, atau aksi atas dokumen terminal (FINAL/REJECTED).
 */
class ApprovalException extends RuntimeException
{
    public static function invalidTransition(string $from, string $action): self
    {
        return new self("Transisi tak valid: tak dapat '{$action}' dari status {$from}.");
    }

    public static function reviewerIsRequester(): self
    {
        return new self('Reviewer ≠ requester: pengaju tak boleh menyetujui dokumennya sendiri.');
    }

    public static function notExpectedApprover(int $level): self
    {
        return new self("Approver tak sesuai rantai untuk level {$level}.");
    }

    public static function noChain(): self
    {
        return new self('Rantai approval tak ditemukan untuk band/scope dokumen ini.');
    }

    public static function noteRequired(): self
    {
        return new self('Catatan wajib saat menolak dokumen.');
    }

    public static function immutable(): self
    {
        return new self('Dokumen FINAL immutable: tak dapat diubah/diproses ulang.');
    }
}
