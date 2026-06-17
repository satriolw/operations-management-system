<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi konfigurasi pencairan & ambang saldo NEVIRA (OPS-1203). Gate master_data.edit.
 * Ambang warning (hari-runway) harus ≥ kritis (warning lebih dini daripada kritis).
 */
class UpdateTopupConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursement_weekdays' => ['required', 'array', 'min:1'],
            'disbursement_weekdays.*' => ['integer', 'between:0,6'],
            'submission_cutoff_lead_hours' => ['required', 'integer', 'between:0,168'],
            'target_ceiling' => ['required', 'integer', 'min:0'],
            'buffer_days' => ['required', 'integer', 'between:0,60'],
            'warning_runway_days' => ['required', 'integer', 'between:0,90'],
            'critical_runway_days' => ['required', 'integer', 'between:0,90'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ((int) $this->input('warning_runway_days') < (int) $this->input('critical_runway_days')) {
                $v->errors()->add('warning_runway_days', 'Ambang warning (hari-runway) harus ≥ ambang kritis.');
            }
        });
    }
}
