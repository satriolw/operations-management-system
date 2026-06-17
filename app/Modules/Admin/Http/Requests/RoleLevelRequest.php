<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi CRUD peta id_role→level (OPS-805). Gate master_data.edit.
 */
class RoleLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('roleLevel')?->id;

        return [
            'id_role' => ['required', 'integer', Rule::unique('nevira_role_levels', 'id_role')->ignore($id)],
            'label' => ['required', 'string', 'max:80'],
            'level' => ['required', 'integer', 'between:0,100'],
            'dual_authority_allowed' => ['required', 'boolean'],
        ];
    }
}
