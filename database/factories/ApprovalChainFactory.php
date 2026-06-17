<?php

namespace Database\Factories;

use App\Models\ApprovalChain;
use App\Modules\Identity\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalChain>
 */
class ApprovalChainFactory extends Factory
{
    protected $model = ApprovalChain::class;

    public function definition(): array
    {
        return [
            'doc_type' => null,
            'amount_band' => 'LOW',
            'scope' => 'OUTLET',
            'level' => 1,
            'approver_role' => Permissions::ROLE_AREA_MANAGER,
            'approver_user_id' => null,
        ];
    }
}
