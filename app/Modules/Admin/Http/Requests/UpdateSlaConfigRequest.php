<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi SLA config outlet (OPS-1302). Gate master_data.edit.
 */
class UpdateSlaConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'sla_clock_mode' => ['required', Rule::in(['business_hours', 'wallclock'])],
            'grace_minutes' => ['required', 'integer', 'between:0,1440'],
            'approaching_lead_minutes' => ['required', 'integer', 'between:0,1440'],
            'stuck_minutes_threshold' => ['required', 'integer', 'between:0,10080'],
            'minor_overdue_minutes' => ['required', 'integer', 'between:0,10080'],
        ];
    }
}
