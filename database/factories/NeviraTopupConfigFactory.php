<?php

namespace Database\Factories;

use App\Models\NeviraTopupConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NeviraTopupConfig>
 */
class NeviraTopupConfigFactory extends Factory
{
    protected $model = NeviraTopupConfig::class;

    public function definition(): array
    {
        return [
            'disbursement_weekdays' => [1, 4], // Senin, Kamis
            'submission_cutoff_lead_hours' => 24,
            'target_ceiling' => 10000000,
            'buffer_days' => 3,
            'warning_runway_days' => 8,
            'critical_runway_days' => 5,
        ];
    }
}
