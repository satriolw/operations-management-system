<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\DeliveryTarget;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ubah deliver_mode per target (OPS-804). Gerbang kesiapan OPS-306 DITEGAKKAN di server:
 * assisted/full_auto hanya bila akun WA OBA siap (OBA aktif & tidak lost). Bukan sekadar UI.
 */
class UpdateTargetModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'deliver_mode' => ['required', 'in:'.implode(',', DeliveryTarget::MODES)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $target = $this->route('target');
            $mode = $this->input('deliver_mode');

            if ($target instanceof DeliveryTarget
                && $target->modeRequiresOba((string) $mode)
                && ! ($target->whatsappAccount?->obaReady() ?? false)) {
                $v->errors()->add('deliver_mode',
                    'Mode ini terkunci: butuh OBA aktif pada nomor pengirim (akun belum siap / nomor lost).');
            }
        });
    }
}
