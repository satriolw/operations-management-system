<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\DeliveryTarget;
use App\Modules\Admin\Http\Requests\DeliveryTargetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * CRUD target pengiriman (OPS-804). Mode assisted/full_auto digerbang OPS-306 di Request.
 */
class DeliveryTargetController extends Controller
{
    public function store(DeliveryTargetRequest $request): RedirectResponse
    {
        DeliveryTarget::create($this->payload($request));

        return back()->with('status', 'Target pengiriman dibuat.');
    }

    public function update(DeliveryTargetRequest $request, DeliveryTarget $target): RedirectResponse
    {
        $target->update($this->payload($request));

        return back()->with('status', 'Target pengiriman diperbarui.');
    }

    public function destroy(DeliveryTarget $target): RedirectResponse
    {
        $target->delete();

        return back()->with('status', 'Target pengiriman dihapus.');
    }

    private function payload(DeliveryTargetRequest $r): array
    {
        return [
            'id_outlet' => (int) $r->input('id_outlet'),
            'investor_id' => $r->input('investor_id') ?: null,
            'investor_label' => $r->input('investor_label') ?: '',
            'channel_type' => $r->input('channel_type') ?: 'whatsapp',
            'whatsapp_account_id' => $r->input('whatsapp_account_id') ?: null,
            'group_id' => $r->input('group_id') ?: null,
            'group_ready' => $r->boolean('group_ready'),
            'deliver_mode' => $r->input('deliver_mode'),
            'template_label' => $r->input('template_label') ?: null,
            'active' => $r->boolean('active'),
        ];
    }
}
