<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Modules\Admin\Http\Requests\UpdateOutletCapacityRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Master data kapasitas outlet (OPS-1101, Epic K). CRUD per outlet (1:1). Effective capacity
 * diturunkan dari input dengan opsi override; ambang overload per outlet. Blade custom
 * (keputusan se-proyek: bukan Filament/Livewire). Dikonsumsi load model OPS-1103.
 */
class CapacityController extends Controller
{
    public function index(): View
    {
        return view('admin.capacity.index', [
            'outlets' => Outlet::query()->with('capacity')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateOutletCapacityRequest $request, Outlet $outlet): RedirectResponse
    {
        $outlet->capacity()->updateOrCreate(
            ['id_outlet' => $outlet->id_outlet],
            [
                'kg_per_day' => $request->input('kg_per_day') ?: null,
                'machines' => $request->input('machines') ?: null,
                'shift_hours' => $request->input('shift_hours') ?: null,
                'throughput_kg_per_machine_hour' => $request->input('throughput_kg_per_machine_hour') ?: null,
                'capacity_kg_per_hour' => $request->input('capacity_kg_per_hour') ?: null,
                'overload_threshold_pct' => (int) $request->input('overload_threshold_pct'),
            ],
        );

        return redirect()
            ->route('admin.capacity.index')
            ->with('status', "Kapasitas {$outlet->name} tersimpan.");
    }
}
