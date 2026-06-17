<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Models\ApprovalChain;
use App\Models\FinancialDocument;
use App\Modules\Admin\Http\Requests\ApprovalChainRequest;
use App\Modules\Identity\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * CRUD master data rantai approval Modul 2 (M2-02, System Design §4). Admin definisikan rantai
 * approver per jenis dokumen × band nominal × scope. Blade custom (keputusan se-proyek).
 * Validasi ≥1 level & ≥1 dari role/user di ApprovalChainRequest; reviewer≠requester runtime = M2-03.
 */
class ApprovalChainController extends Controller
{
    public function index(): View
    {
        return view('admin.approval-chains.index', [
            'chains' => ApprovalChain::query()
                ->orderBy('scope')->orderBy('amount_band')->orderBy('doc_type')->orderBy('level')->get(),
            'docTypes' => FinancialDocument::TYPES,
            'roles' => Permissions::roles(),
        ]);
    }

    public function store(ApprovalChainRequest $request): RedirectResponse
    {
        ApprovalChain::create($this->payload($request));

        return redirect()->route('admin.approval-chains.index')->with('status', 'Level rantai approval ditambahkan.');
    }

    public function update(ApprovalChainRequest $request, ApprovalChain $approvalChain): RedirectResponse
    {
        $approvalChain->update($this->payload($request));

        return redirect()->route('admin.approval-chains.index')->with('status', 'Level rantai approval diperbarui.');
    }

    public function destroy(ApprovalChain $approvalChain): RedirectResponse
    {
        $approvalChain->delete();

        return redirect()->route('admin.approval-chains.index')->with('status', 'Level rantai approval dihapus.');
    }

    private function payload(ApprovalChainRequest $request): array
    {
        return [
            'doc_type' => $request->input('doc_type') ?: null,
            'amount_band' => $request->input('amount_band'),
            'scope' => $request->input('scope'),
            'level' => (int) $request->input('level'),
            'approver_role' => $request->input('approver_role') ?: null,
            'approver_user_id' => $request->input('approver_user_id') ?: null,
        ];
    }
}
