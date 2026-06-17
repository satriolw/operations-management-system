<?php

namespace Database\Factories;

use App\Models\NeviraBalanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NeviraBalanceSnapshot>
 */
class NeviraBalanceSnapshotFactory extends Factory
{
    protected $model = NeviraBalanceSnapshot::class;

    public function definition(): array
    {
        return [
            'captured_at' => now(),
            'saldo_total' => 5000000,
            'breakdown_json' => ['nota_transaksi' => 1000, 'cetak_struk' => 1000],
        ];
    }
}
