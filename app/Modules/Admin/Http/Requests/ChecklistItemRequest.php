<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi tambah item checklist (M3-01). Gate master_data.edit.
 */
class ChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'requires_photo' => ['nullable', 'boolean'],
            'order' => ['nullable', 'integer', 'between:0,99'],
        ];
    }
}
