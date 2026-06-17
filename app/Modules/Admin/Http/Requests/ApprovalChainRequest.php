<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\FinancialDocument;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi CRUD rantai approval (M2-02). Gate master_data.edit. Tiap level wajib mengisi
 * approver_role ATAU approver_user_id (≥1). doc_type null = berlaku semua jenis.
 */
class ApprovalChainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['nullable', Rule::in(FinancialDocument::TYPES)],
            'amount_band' => ['required', Rule::in([FinancialDocument::BAND_LOW, FinancialDocument::BAND_HIGH])],
            'scope' => ['required', Rule::in(['OUTLET', 'HEAD_OFFICE'])],
            'level' => ['required', 'integer', 'between:1,5'],
            'approver_role' => ['nullable', Rule::in(Permissions::roles())],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->filled('approver_role') && ! $this->filled('approver_user_id')) {
                $v->errors()->add('approver_role', 'Isi approver_role ATAU approver_user_id (minimal salah satu).');
            }
        });
    }
}
