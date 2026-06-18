<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\ChecklistTemplate;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi CRUD template checklist (M3-01). Gate master_data.edit.
 */
class ChecklistTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_outlet' => ['nullable', 'integer', 'exists:outlets,id_outlet'],
            'name' => ['required', 'string', 'max:120'],
            'schedule' => ['required', Rule::in(ChecklistTemplate::SCHEDULES)],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
