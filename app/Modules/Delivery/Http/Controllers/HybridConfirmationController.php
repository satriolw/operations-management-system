<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Models\ReportDelivery;
use App\Modules\Identity\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * OPS-302 · Head Store menekan "Sudah saya kirim" → laporan hybrid terverifikasi terkirim ke investor.
 * Menutup celah: app hanya tahu draft sampai Head Store. Watchdog (OPS-704) memakai status konfirmasi ini.
 */
class HybridConfirmationController extends Controller
{
    public function confirm(ReportDelivery $delivery): RedirectResponse
    {
        $user = auth()->user();

        // Gate aksi (APPROVE_AND_SEND) + scoping per-outlet (OPS-1003).
        abort_unless(
            $user?->can(Permissions::APPROVE_AND_SEND) && $user->canAccessOutlet((int) $delivery->id_outlet),
            403,
        );

        // Hanya draft hybrid yang menunggu konfirmasi yang bisa ditandai terkirim.
        abort_unless($delivery->channel === 'hybrid' && $delivery->isAwaitingConfirmation(), 422);

        $delivery->update([
            'status' => ReportDelivery::CONFIRMED_SENT,
            'sent_at' => now(),
        ]);

        return back()->with('status', 'Dikonfirmasi terkirim ke investor.');
    }
}
