<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Models\FinancialDocument;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Daftar/riwayat dokumen keuangan + status tracking (M2-07). Ter-scope per-outlet (OPS-1003):
 * Area Manager hanya outletnya, admin/OM/HoO semua. Filter brand/outlet/jenis/status/band/periode.
 * Detail menampilkan jejak approval (document_approvals).
 */
class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $q = FinancialDocument::query()
            ->visibleTo($request->user())   // scoping per-outlet (fail-closed)
            ->with('requester')
            ->withCount('approvals')
            ->latest();

        foreach (['brand', 'doc_type', 'status', 'amount_band'] as $f) {
            $request->filled($f) && $q->where($f, $request->input($f));
        }
        $request->filled('id_outlet') && $q->where('id_outlet', (int) $request->input('id_outlet'));
        $request->filled('period_start') && $q->whereDate('created_at', '>=', $request->date('period_start'));
        $request->filled('period_end') && $q->whereDate('created_at', '<=', $request->date('period_end'));

        return view('finance.documents.index', [
            'documents' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['brand', 'doc_type', 'status', 'amount_band', 'id_outlet', 'period_start', 'period_end']),
            'docTypes' => FinancialDocument::TYPES,
            'statuses' => ['DRAFT', 'SUBMITTED', 'APPROVED_L1', 'APPROVED_L2', 'FINAL', 'REJECTED'],
        ]);
    }

    public function show(Request $request, FinancialDocument $document): View
    {
        $this->assertVisible($request, $document);

        $document->load(['lines' => fn ($q) => $q->orderBy('sort_order'), 'approvals.approver', 'requester', 'parent', 'outlet']);

        return view('finance.documents.show', ['doc' => $document]);
    }

    /** Otorisasi baca per-outlet (OPS-1003). HEAD_OFFICE → butuh akses-semua. */
    private function assertVisible(Request $request, FinancialDocument $document): void
    {
        $user = $request->user();
        $ok = $document->id_outlet === null
            ? $user->canAccessAllOutlets()
            : $user->canAccessOutlet((int) $document->id_outlet);

        abort_unless($ok, 403);
    }
}
