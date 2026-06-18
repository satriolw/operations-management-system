<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CRUD akun WhatsApp (OPS-804). Gate master_data.edit. credentials_ref = REFERENSI ke secret store
 * (mis. nama secret), BUKAN secret mentah — aturan emas #7.
 */
class WhatsappAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'id_outlet' => ['nullable', 'integer', 'exists:outlets,id_outlet'],
            'phone_number' => ['required', 'string', 'max:30'],
            'provider' => ['nullable', 'string', 'max:40'],
            'oba_status' => ['required', Rule::in(['active', 'process', 'none'])],
            'account_status' => ['required', Rule::in(['active', 'lost', 'recovering'])],
            'credentials_ref' => ['nullable', 'string', 'max:120'], // referensi secret store, bukan secret
            'active' => ['nullable', 'boolean'],
        ];
    }
}
