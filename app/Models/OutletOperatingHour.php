<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jam operasional per weekday (OPS-803/OPS-106). */
class OutletOperatingHour extends Model
{
    protected $fillable = ['id_outlet', 'weekday', 'is_closed', 'open_time', 'close_time'];

    protected $casts = [
        'weekday' => 'integer',
        'is_closed' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
