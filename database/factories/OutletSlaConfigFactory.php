<?php

namespace Database\Factories;

use App\Models\OutletSlaConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OutletSlaConfig> */
class OutletSlaConfigFactory extends Factory
{
    protected $model = OutletSlaConfig::class;

    public function definition(): array
    {
        return [
            'sla_clock_mode' => 'wallclock',
            'grace_minutes' => 30,
            'approaching_lead_minutes' => 120,
            'stuck_minutes_threshold' => 240,
            'minor_overdue_minutes' => 120,
        ];
    }
}
