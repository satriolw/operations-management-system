<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Baseline transaksi per titik cek (OPS-101 schema, dihitung OPS-501). */
class OutletBaseline extends Model
{
    protected $fillable = ['id_outlet', 'checkpoint_hour', 'avg_txn', 'sample_days'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
