<?php

namespace App\Modules\Signals;

use App\Modules\Ingestion\DTO\TransactionDTO;
use App\Modules\Signals\Contracts\ReplacementMatcher;

/**
 * Default OPS-604: field "nota pengganti" belum ada & matching by-customer dilarang (PII).
 * Anggap tak ada pengganti → semua void/refund produksi (progress>0) jadi kandidat "perlu ditinjau".
 * Ganti ke heuristik/field terstruktur kelak via binding (SignalsServiceProvider).
 */
final class NullReplacementMatcher implements ReplacementMatcher
{
    public function hasReplacement(TransactionDTO $voided): bool
    {
        return false;
    }
}
