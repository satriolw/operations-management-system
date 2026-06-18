<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Outlet;
use App\Models\TransactionAuditConfig;
use App\Modules\Admin\Http\Requests\UpdateAuditConfigRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * CRUD ambang audit transaksi per outlet (Epic N, OPS-1402..1406). Blade custom; gate
 * master_data.edit. Dikonsumsi detektor audit (PromoLeakageDetector dst).
 */
class AuditConfigController extends Controller
{
    public function index(): View
    {
        $configs = TransactionAuditConfig::query()->get()->keyBy('id_outlet');

        return view('admin.audit-config.index', [
            'outlets' => Outlet::query()->orderBy('name')->get(),
            'config' => fn (int $id) => $configs->get($id) ?? TransactionAuditConfig::forOutlet($id),
            'reviewMode' => (bool) config('transaction_audit.review_mode', true),
        ]);
    }

    public function update(UpdateAuditConfigRequest $request, Outlet $outlet): RedirectResponse
    {
        TransactionAuditConfig::updateOrCreate(['id_outlet' => $outlet->id_outlet], $request->validated());

        return redirect()->route('admin.audit-config.index')->with('status', "Ambang audit {$outlet->name} tersimpan.");
    }
}
