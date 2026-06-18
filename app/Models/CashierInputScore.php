<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * KPI akurasi input per kasir (OPS-603, OPS-101 schema). id_cashier = aktor NEVIRA pembuat nota.
 */
class CashierInputScore extends Model
{
    protected $fillable = ['id_outlet', 'id_cashier', 'period', 'error_count', 'txn_count', 'rate', 'qty_variance_count'];

    protected $casts = [
        'error_count' => 'integer',
        'txn_count' => 'integer',
        'qty_variance_count' => 'integer', // OPS-1405 variance quantity (memperkuat KPI input)
    ];
}
