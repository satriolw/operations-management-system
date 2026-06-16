<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Modules\Admin\Http\Requests\UpdateOutletRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Edit Outlet (OPS-803): jam laporan, status aktif, titik cek diam (dinamis) + ambang,
 * jam operasional, hari libur. Memindahkan checkpoint dari hardcode ke konfigurasi.
 */
class OutletController extends Controller
{
    public function edit(Outlet $outlet): View
    {
        $outlet->load(['checkpoints', 'operatingHours', 'holidays']);

        return view('admin.outlets.edit', [
            'outlet' => $outlet,
            'hasBaseline' => $outlet->hasBaseline(), // false → outlet baru, beri catatan
            'weekdays' => ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
        ]);
    }

    public function update(UpdateOutletRequest $request, Outlet $outlet): RedirectResponse
    {
        DB::transaction(function () use ($request, $outlet) {
            $outlet->update([
                'active' => $request->boolean('active'),
                'report_time' => $request->input('report_time'),
            ]);

            // Ganti penuh child rows dari payload (perubahan berlaku tanpa deploy).
            $outlet->checkpoints()->delete();
            foreach ($request->input('checkpoints', []) as $c) {
                $outlet->checkpoints()->create([
                    'checkpoint_hour' => (int) $c['hour'],
                    'threshold_pct' => (int) $c['threshold'],
                ]);
            }

            $outlet->operatingHours()->delete();
            foreach ($request->input('operating_hours', []) as $w) {
                $outlet->operatingHours()->create([
                    'weekday' => (int) $w['weekday'],
                    'open_time' => $w['open'],
                    'close_time' => $w['close'],
                ]);
            }

            $outlet->holidays()->delete();
            foreach ($request->input('holidays', []) as $h) {
                $outlet->holidays()->create([
                    'holiday_date' => $h['date'],
                    'note' => $h['note'] ?? null,
                ]);
            }
        });

        return redirect()
            ->route('admin.outlets.edit', $outlet)
            ->with('status', 'Perubahan outlet tersimpan.');
    }
}
