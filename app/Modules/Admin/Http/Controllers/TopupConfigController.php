<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\NeviraTopupConfig;
use App\Modules\Admin\Http\Requests\UpdateTopupConfigRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Master data kalender pencairan + ambang saldo NEVIRA (OPS-1203, Epic L). Singleton tingkat-
 * merchant. Hari pencairan & ambang runway diubah tanpa deploy. Blade custom (keputusan se-proyek).
 */
class TopupConfigController extends Controller
{
    public function index(): View
    {
        return view('admin.topup-config.index', [
            'config' => NeviraTopupConfig::current(),
            'weekdayLabels' => [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'],
        ]);
    }

    public function update(UpdateTopupConfigRequest $request): RedirectResponse
    {
        NeviraTopupConfig::current()->update([
            'disbursement_weekdays' => array_values(array_map('intval', $request->input('disbursement_weekdays', []))),
            'submission_cutoff_lead_hours' => (int) $request->input('submission_cutoff_lead_hours'),
            'target_ceiling' => (int) $request->input('target_ceiling'),
            'buffer_days' => (int) $request->input('buffer_days'),
            'warning_runway_days' => (int) $request->input('warning_runway_days'),
            'critical_runway_days' => (int) $request->input('critical_runway_days'),
        ]);

        return redirect()
            ->route('admin.topup-config.index')
            ->with('status', 'Konfigurasi pencairan saldo tersimpan.');
    }
}
