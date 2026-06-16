<?php

namespace Database\Factories;

use App\Models\DeliveryTarget;
use App\Models\Outlet;
use App\Models\WhatsappAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryTarget>
 */
class DeliveryTargetFactory extends Factory
{
    protected $model = DeliveryTarget::class;

    public function definition(): array
    {
        return [
            'id_outlet' => Outlet::factory(),
            'investor_label' => 'Pak '.$this->faker->firstName(),
            'channel_type' => 'whatsapp',
            'whatsapp_account_id' => WhatsappAccount::factory(),
            'group_id' => null,
            'group_ready' => false,
            'deliver_mode' => 'hybrid',
            'template_label' => 'Harian v2',
            'active' => true,
        ];
    }
}
