<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\DeliveryTarget;
use App\Models\WhatsappAccount;
use App\Modules\Admin\Http\Requests\UpdateTargetModeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Layar "Akun WhatsApp & Target Pengiriman" (OPS-804). List akun WA (kredensial tertutup)
 * + target dgn mode per target. Perubahan mode lewat gerbang kesiapan (OPS-306).
 */
class DeliveryConfigController extends Controller
{
    public function index(): View
    {
        $accounts = WhatsappAccount::query()->with('outlet')->orderBy('label')->get();
        $targets = DeliveryTarget::query()->with(['outlet', 'whatsappAccount'])->orderBy('id')->get();

        return view('admin.delivery.index', [
            'accounts' => $accounts,
            'targets' => $targets,
            'hasLost' => $accounts->contains(fn (WhatsappAccount $a) => $a->isLost()),
            'modes' => [
                'hybrid' => ['name' => 'Hybrid', 'desc' => 'Konfirmasi manual oleh Head Store. Selalu tersedia.', 'oba' => false],
                'assisted' => ['name' => 'Assisted', 'desc' => 'Satu klik setujui — sistem mengirim via OBA.', 'oba' => true],
                'full_auto' => ['name' => 'Full auto', 'desc' => 'Terjadwal otomatis tanpa intervensi via OBA.', 'oba' => true],
            ],
        ]);
    }

    public function updateMode(UpdateTargetModeRequest $request, DeliveryTarget $target): RedirectResponse
    {
        $target->update(['deliver_mode' => $request->input('deliver_mode')]);

        return redirect()
            ->route('admin.delivery.index')
            ->with('status', "Mode {$target->outlet?->name} diubah ke {$target->deliver_mode}.");
    }
}
