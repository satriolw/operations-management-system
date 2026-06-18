<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\WhatsappAccount;
use App\Modules\Admin\Http\Requests\WhatsappAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * CRUD akun WhatsApp (OPS-804). credentials_ref = referensi secret store, bukan secret mentah.
 */
class WhatsappAccountController extends Controller
{
    public function store(WhatsappAccountRequest $request): RedirectResponse
    {
        WhatsappAccount::create($this->payload($request));

        return back()->with('status', 'Akun WhatsApp dibuat.');
    }

    public function update(WhatsappAccountRequest $request, WhatsappAccount $whatsappAccount): RedirectResponse
    {
        $whatsappAccount->update($this->payload($request));

        return back()->with('status', 'Akun WhatsApp diperbarui.');
    }

    public function destroy(WhatsappAccount $whatsappAccount): RedirectResponse
    {
        $whatsappAccount->delete();

        return back()->with('status', 'Akun WhatsApp dihapus.');
    }

    private function payload(WhatsappAccountRequest $r): array
    {
        return [
            'label' => $r->input('label'),
            'id_outlet' => $r->input('id_outlet') ?: null,
            'phone_number' => $r->input('phone_number'),
            'provider' => $r->input('provider') ?: 'meta_cloud',
            'oba_status' => $r->input('oba_status'),
            'account_status' => $r->input('account_status'),
            'credentials_ref' => $r->input('credentials_ref') ?: null,
            'active' => $r->boolean('active'),
        ];
    }
}
