<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koreksi revenue dari VOID/REFUND (OPS-101 schema, diisi OPS-401/402). Referensi NEVIRA,
 * bukan salinan transaksi. Ber-id_outlet → ter-scope per outlet (OPS-1003).
 */
class RevenueAdjustment extends Model
{
    use ScopedByOutlet;

    protected $fillable = [
        'id_outlet', 'report_run_id', 'transaction_number', 'type', 'amount',
        'reason', 'nota_date', 'approved_at', 'restated_for_date',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
