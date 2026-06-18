<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Models\OutletSlaConfig;
use App\Modules\Admin\Http\Requests\UpdateSlaConfigRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * CRUD master data SLA produksi per outlet (OPS-1302, Epic M). Mode jam + ambang per outlet.
 * Blade custom; gate master_data.edit. Dikonsumsi LateOrderDetector (OPS-1303).
 */
class SlaConfigController extends Controller
{
    public function index(): View
    {
        $outlets = Outlet::query()->orderBy('name')->get();
        $configs = OutletSlaConfig::query()->get()->keyBy('id_outlet');

        return view('admin.sla-config.index', [
            'outlets' => $outlets,
            'config' => fn (int $id) => $configs->get($id) ?? OutletSlaConfig::forOutlet($id),
        ]);
    }

    public function update(UpdateSlaConfigRequest $request, Outlet $outlet): RedirectResponse
    {
        OutletSlaConfig::updateOrCreate(
            ['id_outlet' => $outlet->id_outlet],
            $request->validated(),
        );

        return redirect()->route('admin.sla-config.index')->with('status', "SLA {$outlet->name} tersimpan.");
    }
}
