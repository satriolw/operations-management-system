<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hari libur spesifik per outlet (OPS-803/OPS-106). */
class OutletHoliday extends Model
{
    protected $fillable = ['id_outlet', 'holiday_date', 'note'];

    protected $casts = [
        'holiday_date' => 'date',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
