<?php

namespace App\Modules\Signals;

use App\Models\NeviraBalanceSnapshot;
use App\Modules\Ingestion\Contracts\TransactionSource;
use App\Modules\Ingestion\DTO\DateRange;
use App\Support\Time\Wib;

/**
 * Tangkap snapshot saldo merchant berkala (OPS-1201, Epic L). Memanggil merchantBalance (anti-
 * corruption) lalu persist saldo_total + breakdown ber-stempel waktu WIB. Burn/runway (OPS-1202)
 * memakai deret snapshot ini. Tidak menarik history NEVIRA.
 */
final class BalanceSnapshotService
{
    public function __construct(private readonly TransactionSource $source) {}

    public function capture(?string $date = null): NeviraBalanceSnapshot
    {
        $date ??= Wib::normalize(now())->format('Y-m-d');
        $dto = $this->source->merchantBalance(new DateRange($date, $date));

        return NeviraBalanceSnapshot::create([
            'captured_at' => Wib::normalize(now()),
            'saldo_total' => $dto->saldoTotal,
            'breakdown_json' => $dto->breakdown,
        ]);
    }
}
