<?php

namespace Database\Factories;

use App\Models\TransactionAuditConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TransactionAuditConfig> */
class TransactionAuditConfigFactory extends Factory
{
    protected $model = TransactionAuditConfig::class;

    public function definition(): array
    {
        return [
            'promo_leak_pct' => 15, 'promo_leak_daily_cap' => 500000,
            'payment_anomaly_min_amount' => 50000, 'offprice_tolerance_pct' => 5,
            'qty_variance_pct' => 20, 'deposit_expiry_lead_days' => 14,
        ];
    }
}
