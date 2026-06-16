<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Titik cek outlet-diam (OPS-803). Dibaca OPS-502. */
class OutletCheckpoint extends Model
{
    protected $fillable = ['id_outlet', 'check_time'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
