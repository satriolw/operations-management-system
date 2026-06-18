<?php

namespace App\Modules\Admin\Http\Requests;

use App\Models\DeliveryTarget;
use App\Models\WhatsappAccount;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CRUD target pengiriman (OPS-804). Gate master_data.edit. Gerbang kesiapan OPS-306: deliver_mode
 * assisted/full_auto hanya bila akun WA OBA siap (ditegakkan server, bukan UI).
 */
class DeliveryTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::EDIT_MASTER_DATA) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_outlet' => ['required', 'integer', 'exists:outlets,id_outlet'],
            'investor_id' => ['nullable', 'integer', 'exists:investors,id'],
            'investor_label' => ['nullable', 'string', 'max:80'],
            'channel_type' => ['nullable', 'string', 'max:20'],
            'whatsapp_account_id' => ['nullable', 'integer', 'exists:whatsapp_accounts,id'],
            'group_id' => ['nullable', 'string', 'max:80'],
            'group_ready' => ['nullable', 'boolean'],
            'deliver_mode' => ['required', Rule::in(DeliveryTarget::MODES)],
            'template_label' => ['nullable', 'string', 'max:80'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $mode = (string) $this->input('deliver_mode');
            if (! in_array($mode, ['assisted', 'full_auto'], true)) {
                return;
            }
            $acc = $this->input('whatsapp_account_id') ? WhatsappAccount::find($this->input('whatsapp_account_id')) : null;
            if (! ($acc?->obaReady() ?? false)) {
                $v->errors()->add('deliver_mode', 'Mode assisted/full_auto butuh akun WA OBA siap (OPS-306). Pilih hybrid atau akun OBA aktif.');
            }
        });
    }
}
