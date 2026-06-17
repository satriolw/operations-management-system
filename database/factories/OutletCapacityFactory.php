<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\OutletCapacity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletCapacity>
 */
class OutletCapacityFactory extends Factory
{
    protected $model = OutletCapacity::class;

    public function definition(): array
    {
        return [
            'id_outlet' => Outlet::factory(),
            'kg_per_day' => 400,
            'machines' => 4,
            'shift_hours' => 10,
            'throughput_kg_per_machine_hour' => 10,
            'capacity_kg_per_hour' => null,
            'overload_threshold_pct' => 80,
        ];
    }
}
