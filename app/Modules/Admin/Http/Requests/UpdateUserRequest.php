<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edit user (OPS-802). Email immutable (tidak divalidasi/ubah). Role + outlet assignment.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:'.implode(',', Permissions::roles())],
            'outlets' => ['array'],
            'outlets.*' => ['integer', 'exists:outlets,id_outlet'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => validateRoleScope($v, $this->input('role'), $this->input('outlets', [])));
    }
}
