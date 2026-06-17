<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot saldo merchant NEVIRA (OPS-1201, Epic L). Hanya saldo_total + breakdown, ber-stempel
 * waktu (WIB). Burn/runway (OPS-1202) = delta antar-snapshot. Tanpa PII.
 */
class NeviraBalanceSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['captured_at', 'saldo_total', 'breakdown_json'];

    protected $casts = [
        'captured_at' => 'datetime',
        'saldo_total' => 'integer',
        'breakdown_json' => 'array',
    ];
}
