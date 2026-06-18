<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\ReportTemplate;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Buat template laporan (OPS-901). Gate master_data.edit. Master = grup; override butuh id_outlet
 * + parent (pewarisan System Design §3.9). Layout disusun nanti di builder (OPS-902) — di sini cukup
 * shell valid agar pipeline tak terblokir.
 */
class ReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in([ReportTemplate::SCOPE_MASTER, ReportTemplate::SCOPE_OUTLET, ReportTemplate::SCOPE_TARGET])],
            'name' => ['required', 'string', 'max:120'],
            'id_outlet' => ['nullable', 'integer', 'exists:outlets,id_outlet'],
            'parent_template_id' => ['nullable', 'integer', 'exists:report_templates,id'],
            'meta_template_ref' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // Override (outlet/target) wajib punya id_outlet + parent master yang diwarisi.
            if (in_array($this->input('scope'), [ReportTemplate::SCOPE_OUTLET, ReportTemplate::SCOPE_TARGET], true)) {
                if (! $this->filled('id_outlet')) {
                    $v->errors()->add('id_outlet', 'Override per-outlet wajib menyertakan id_outlet.');
                }
                if (! $this->filled('parent_template_id')) {
                    $v->errors()->add('parent_template_id', 'Override wajib mewarisi satu template master.');
                }
            }
        });
    }
}
