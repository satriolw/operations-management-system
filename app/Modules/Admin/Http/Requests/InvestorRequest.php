<?php

namespace App\Modules\Admin\Http\Requests;

use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CRUD investor (OPS-1005). 1:1 outlet (id_outlet unique). Gate master_data.edit. Investor TIDAK
 * login (terima laporan via WA) — ini hanya master ringan untuk re-invite/DR.
 */
class InvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('investor')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'wa_contact' => ['nullable', 'string', 'max:30'],
            'id_outlet' => ['required', 'integer', 'exists:outlets,id_outlet', Rule::unique('investors', 'id_outlet')->ignore($id)],
            'since_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
