<?php

namespace Database\Factories;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    protected $model = ChecklistItem::class;

    public function definition(): array
    {
        return [
            'template_id' => ChecklistTemplate::factory(),
            'label' => 'Cek mesin',
            'requires_photo' => true,
            'order' => 0,
        ];
    }
}
