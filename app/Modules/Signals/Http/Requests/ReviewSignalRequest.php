<?php

namespace App\Modules\Signals\Http\Requests;

use App\Models\SignalEvent;
use App\Modules\Identity\Permissions;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi tinjauan sinyal (OPS-606). Gate REVIEW_SIGNALS + scoping per-outlet. Catatan WAJIB.
 */
class ReviewSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $signal = $this->route('signal');

        return $user?->can(Permissions::REVIEW_SIGNALS)
            && $signal instanceof SignalEvent
            && $user->canAccessOutlet((int) $signal->id_outlet);
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:wajar,ditindaklanjuti,eskalasi'],
            'note' => ['required', 'string', 'min:3', 'max:1000'], // catatan wajib
            'evidence_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
