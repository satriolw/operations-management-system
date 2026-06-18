<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ambang audit transaksi per outlet (Epic N). Default aman bila outlet belum dikonfigurasi.
 */
class TransactionAuditConfig extends Model
{
    use HasFactory;

    protected $table = 'transaction_audit_config';

    protected $fillable = [
        'id_outlet', 'promo_leak_pct', 'promo_leak_daily_cap', 'payment_anomaly_min_amount',
        'offprice_tolerance_pct', 'qty_variance_pct', 'deposit_expiry_lead_days',
    ];

    protected $casts = [
        'promo_leak_pct' => 'float',
        'promo_leak_daily_cap' => 'integer',
        'payment_anomaly_min_amount' => 'integer',
        'offprice_tolerance_pct' => 'float',
        'qty_variance_pct' => 'float',
        'deposit_expiry_lead_days' => 'integer',
    ];

    public static function forOutlet(int $idOutlet): self
    {
        return static::query()->firstWhere('id_outlet', $idOutlet)
            ?? (new self(['id_outlet' => $idOutlet] + (array) config('transaction_audit.defaults', [])));
    }
}
