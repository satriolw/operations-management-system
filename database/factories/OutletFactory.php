<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    protected $model = Outlet::class;

    public function definition(): array
    {
        return [
            'id_outlet' => $this->faker->unique()->numberBetween(100, 999),
            'name' => $this->faker->city(),
            'report_time' => '21:00',
            'timezone' => 'Asia/Jakarta',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
