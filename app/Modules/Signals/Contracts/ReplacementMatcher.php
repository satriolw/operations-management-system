<?php

namespace App\Modules\Signals\Contracts;

use App\Modules\Ingestion\DTO\TransactionDTO;

/**
 * Apakah ada "nota pengganti" untuk produksi yang di-void/refund (OPS-604).
 *
 * Field "nota pengganti" NEVIRA belum tersedia → default NullReplacementMatcher (selalu false:
 * tak ada bukti pengganti → kandidat orphaned). Diganti ke matcher heuristik (customer+nominal+waktu)
 * atau field terstruktur saat tersedia — TANPA mengubah detector (abstraksi).
 */
interface ReplacementMatcher
{
    public function hasReplacement(TransactionDTO $voided): bool;
}
