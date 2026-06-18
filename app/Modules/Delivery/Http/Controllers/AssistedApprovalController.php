<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Models\DeliveryTarget;
use App\Models\ReportDelivery;
use App\Modules\Delivery\AssistedApproval;
use App\Modules\Delivery\CloudApiDeliverer;
use App\Modules\Identity\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * OPS-304 · Head Store menekan "Setujui & Kirim" (assisted) → app kirim laporan via Cloud API.
 * Gate APPROVE_AND_SEND + scoping per-outlet (OPS-1003). Hanya draft assisted (cloud_api,
 * awaiting_approval) yang bisa disetujui. Gagal Cloud API → fallback hybrid (di service).
 */
class AssistedApprovalController extends Controller
{
    public function approveSend(ReportDelivery $delivery, AssistedApproval $service): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(
            $user?->can(Permissions::APPROVE_AND_SEND) && $user->canAccessOutlet((int) $delivery->id_outlet),
            403,
        );

        // Hanya draft assisted yang menunggu persetujuan.
        abort_unless($delivery->channel === CloudApiDeliverer::CHANNEL && $delivery->isAwaitingApproval(), 422);

        // Investor 1:1 outlet → satu target per outlet.
        $target = DeliveryTarget::where('id_outlet', $delivery->id_outlet)->first();
        abort_unless($target !== null, 422);

        $result = $service->approveAndSend($delivery, $target);

        $msg = $result->status === ReportDelivery::SENT
            ? 'Disetujui & terkirim ke investor via WhatsApp.'
            : 'Cloud API tak tersedia — dialihkan ke mode hybrid (kirim manual lalu konfirmasi).';

        return back()->with('status', $msg);
    }
}
