<?php

namespace App\Modules\Reporting;

/**
 * Keputusan apakah & bagaimana laporan dikirim untuk satu (outlet, tanggal) — OPS-1001.
 *  - NORMAL: kirim biasa.
 *  - EMPTY_STATE: outlet buka tapi nol transaksi → TETAP kirim dgn catatan jujur + alert internal.
 *  - SUPPRESS: outlet tutup/libur → jangan kirim "Rp0" polos.
 */
final class ReportDecision
{
    public const NORMAL = 'normal';
    public const EMPTY_STATE = 'empty_state';
    public const SUPPRESS = 'suppress';

    public function __construct(
        public readonly string $action,
        public readonly ?string $note = null,
    ) {}

    public function shouldSuppress(): bool
    {
        return $this->action === self::SUPPRESS;
    }

    public function isEmptyState(): bool
    {
        return $this->action === self::EMPTY_STATE;
    }
}
