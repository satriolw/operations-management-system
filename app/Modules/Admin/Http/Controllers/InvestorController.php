<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\Investor;
use App\Modules\Admin\Http\Requests\InvestorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * CRUD investor (OPS-1005), 1:1 outlet. Investor tidak login; master ringan untuk re-invite/DR.
 */
class InvestorController extends Controller
{
    public function store(InvestorRequest $request): RedirectResponse
    {
        Investor::create($this->payload($request));

        return back()->with('status', 'Investor dibuat.');
    }

    public function update(InvestorRequest $request, Investor $investor): RedirectResponse
    {
        $investor->update($this->payload($request));

        return back()->with('status', 'Investor diperbarui.');
    }

    public function destroy(Investor $investor): RedirectResponse
    {
        $investor->delete();

        return back()->with('status', 'Investor dihapus.');
    }

    private function payload(InvestorRequest $r): array
    {
        return [
            'name' => $r->input('name'),
            'wa_contact' => $r->input('wa_contact') ?: null,
            'id_outlet' => (int) $r->input('id_outlet'),
            'since_date' => $r->input('since_date') ?: null,
            'notes' => $r->input('notes') ?: null,
            'active' => $r->boolean('active'),
        ];
    }
}
