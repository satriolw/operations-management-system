<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi kapasitas outlet (OPS-1101). Gate: master_data.edit. Effective capacity harus
 * dapat diturunkan dari salah satu jalur (override, mesin×throughput, atau kg-hari + jam-shift).
 */
class UpdateOutletCapacityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'kg_per_day' => ['nullable', 'numeric', 'min:0'],
            'machines' => ['nullable', 'integer', 'min:0'],
            'shift_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'throughput_kg_per_machine_hour' => ['nullable', 'numeric', 'min:0'],
            'capacity_kg_per_hour' => ['nullable', 'numeric', 'min:0'], // override
            'overload_threshold_pct' => ['required', 'integer', 'between:1,100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->capacityDerivable()) {
                $v->errors()->add('capacity_kg_per_hour',
                    'Kapasitas tak dapat dihitung. Isi salah satu: override kg/jam, ATAU mesin + throughput, ATAU kg/hari + jam shift.');
            }
        });
    }

    /** Minimal satu jalur turunan terisi (cermin OutletCapacity::effectiveKgPerHour). */
    private function capacityDerivable(): bool
    {
        if ((float) $this->input('capacity_kg_per_hour') > 0) {
            return true;
        }
        if ((int) $this->input('machines') > 0 && (float) $this->input('throughput_kg_per_machine_hour') > 0) {
            return true;
        }

        return (float) $this->input('kg_per_day') > 0 && (float) $this->input('shift_hours') > 0;
    }
}
