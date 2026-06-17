<?php

namespace Database\Factories;

use App\Models\FinancialDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialDocument>
 */
class FinancialDocumentFactory extends Factory
{
    protected $model = FinancialDocument::class;

    public function definition(): array
    {
        $amount = $this->faker->numberBetween(50000, 5000000);

        return [
            'doc_type' => 'PAYMENT_REQUEST',
            'brand' => 'LW',
            'id_outlet' => null,
            'scope' => 'OUTLET',
            'requester_user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'amount' => $amount,
            'amount_band' => FinancialDocument::bandFor($amount),
            'cost_center' => 'OPS',
            'currency' => 'IDR',
            'status' => FinancialDocument::STATUS_DRAFT,
            'current_level' => 0,
        ];
    }

    public function headOffice(): static
    {
        return $this->state(fn () => ['scope' => 'HEAD_OFFICE', 'id_outlet' => null]);
    }
}
