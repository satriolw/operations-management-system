<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Undang user (OPS-802). Gate master_data.edit. Outlet assignment menyalakan scoping (OPS-1003)
 * sesuai scope role: admin=all (tanpa assign), head_store=tepat 1, area/ops=>=1.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
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

/**
 * Validasi scope outlet sesuai role (dipakai store & update).
 */
function validateRoleScope(Validator $v, ?string $role, array $outlets): void
{
    if ($role === null) {
        return;
    }
    $scope = Permissions::scopeFor($role);

    if ($scope === 'single' && count($outlets) !== 1) {
        $v->errors()->add('outlets', 'Role ini di-scope ke tepat satu outlet.');
    }
    if ($scope === 'multi' && count($outlets) < 1) {
        $v->errors()->add('outlets', 'Pilih minimal satu outlet untuk scoping akses.');
    }
    // scope 'all' (admin) → outlets diabaikan
}
