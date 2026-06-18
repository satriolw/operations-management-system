<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi ambang audit transaksi per outlet (Epic N). Gate master_data.edit.
 */
class UpdateAuditConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'promo_leak_pct' => ['required', 'numeric', 'between:0,100'],
            'promo_leak_daily_cap' => ['required', 'integer', 'min:0'],
            'payment_anomaly_min_amount' => ['required', 'integer', 'min:0'],
            'offprice_tolerance_pct' => ['required', 'numeric', 'between:0,100'],
            'qty_variance_pct' => ['required', 'numeric', 'between:0,100'],
            'deposit_expiry_lead_days' => ['required', 'integer', 'between:0,365'],
        ];
    }
}
