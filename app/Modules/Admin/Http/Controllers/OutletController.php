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

        // Jam operasional ter-index per weekday utk render 7 baris (Senin..Minggu sesuai desain).
        $hoursByDay = $outlet->operatingHours->keyBy('weekday');

        return view('admin.outlets.edit', [
            'outlet' => $outlet,
            'hasBaseline' => $outlet->hasBaseline(), // false → outlet baru, beri catatan
            'hoursByDay' => $hoursByDay,
            // urutan tampil Senin..Minggu → [weekday int => label]
            'weekdayOrder' => [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'],
            'comparisonOptions' => [
                'avg_14d' => 'Rata-rata 14 hari (per jam)',
                'avg_30d' => 'Rata-rata 30 hari (per jam)',
                'same_dow' => 'Hari yang sama minggu lalu',
            ],
        ]);
    }

    public function update(UpdateOutletRequest $request, Outlet $outlet): RedirectResponse
    {
        DB::transaction(function () use ($request, $outlet) {
            $outlet->update([
                'active' => $request->boolean('active'),
                'report_time' => $request->input('report_time'),
                'silent_threshold_pct' => (int) $request->input('silent_threshold_pct'),
                'comparison_basis' => $request->input('comparison_basis'),
            ]);

            // Ganti penuh child rows dari payload (perubahan berlaku tanpa deploy).
            $outlet->checkpoints()->delete();
            foreach ($request->input('checkpoints', []) as $c) {
                $outlet->checkpoints()->create(['check_time' => $c['time']]);
            }

            $outlet->operatingHours()->delete();
            foreach ($request->input('operating_hours', []) as $w) {
                $closed = filter_var($w['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $outlet->operatingHours()->create([
                    'weekday' => (int) $w['weekday'],
                    'is_closed' => $closed,
                    'open_time' => $closed ? null : ($w['open'] ?? null),
                    'close_time' => $closed ? null : ($w['close'] ?? null),
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
