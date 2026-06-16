<?php

namespace Database\Factories;

use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappAccount>
 */
class WhatsappAccountFactory extends Factory
{
    protected $model = WhatsappAccount::class;

    public function definition(): array
    {
        return [
            'label' => 'LW '.$this->faker->city(),
            'phone_number' => '+6281'.$this->faker->numerify('########'),
            'provider' => 'meta_cloud',
            'oba_status' => 'active',
            'account_status' => 'active',
            'credentials_ref' => 'secret://wa/'.$this->faker->uuid(),
            'active' => true,
        ];
    }

    public function obaNone(): static
    {
        return $this->state(fn () => ['oba_status' => 'none']);
    }

    public function obaProcess(): static
    {
        return $this->state(fn () => ['oba_status' => 'process']);
    }

    public function lost(): static
    {
        return $this->state(fn () => ['account_status' => 'lost']);
    }
}
