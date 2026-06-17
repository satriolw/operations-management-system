<?php

namespace Database\Factories;

use App\Models\NeviraCostByOutlet;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NeviraCostByOutlet>
 */
class NeviraCostByOutletFactory extends Factory
{
    protected $model = NeviraCostByOutlet::class;

    public function definition(): array
    {
        return [
            'id_outlet' => Outlet::factory(),
            'period' => '2026-06-01_2026-06-30',
            'counts_json' => ['nota_transaksi' => 100, 'cetak_struk' => 100],
            'total_cost' => 15000,
            'flagged' => false,
        ];
    }
}
