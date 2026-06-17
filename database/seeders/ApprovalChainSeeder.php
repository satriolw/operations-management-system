<?php

namespace Database\Seeders;

use App\Models\ApprovalChain;
use App\Models\FinancialDocument;
use App\Modules\Identity\Permissions;
use Illuminate\Database\Seeder;

/**
 * Default rantai approval Modul 2 (M2-02, System Design §4). Berlaku semua jenis (doc_type null):
 *   LOW  (<Rp1jt): L1 Area Manager       → L2 Operations Manager
 *   HIGH (≥Rp1jt): L1 Operations Manager → L2 Head of Operations
 * Idempoten (firstOrCreate). Disesuaikan via Admin (M2-02 CRUD).
 */
class ApprovalChainSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            FinancialDocument::BAND_LOW => [1 => Permissions::ROLE_AREA_MANAGER, 2 => Permissions::ROLE_OPERATIONS_MANAGER],
            FinancialDocument::BAND_HIGH => [1 => Permissions::ROLE_OPERATIONS_MANAGER, 2 => Permissions::ROLE_HEAD_OF_OPERATIONS],
        ];

        foreach (['OUTLET', 'HEAD_OFFICE'] as $scope) {
            foreach ($matrix as $band => $levels) {
                foreach ($levels as $level => $role) {
                    ApprovalChain::firstOrCreate(
                        ['doc_type' => null, 'amount_band' => $band, 'scope' => $scope, 'level' => $level],
                        ['approver_role' => $role, 'approver_user_id' => null],
                    );
                }
            }
        }
    }
}
