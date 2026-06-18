<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Models\ReportRun;
use App\Modules\Identity\Permissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Preview & Kirim Laporan (OPS-302 hybrid). Daftar report_runs ter-scope per-outlet (OPS-1003).
 * Detail menampilkan konten laporan (payload_text yang dirender dari template) + status tiap
 * pengiriman. Untuk draft hybrid yang menunggu, Head Store (APPROVE_AND_SEND) menekan
 * "Sudah saya kirim" lewat rute deliveries.confirm yang sudah ada.
 *
 * Layar ini READ + aksi konfirmasi-saja: tak menyusun laporan (itu pipeline scheduler/job).
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $q = ReportRun::query()
            ->visibleTo($request->user())   // scoping per-outlet (fail-closed)
            ->with(['outlet', 'deliveries'])
            ->orderByDesc('report_date');

        $request->filled('status') && $q->where('status', $request->input('status'));
        $request->filled('id_outlet') && $q->where('id_outlet', (int) $request->input('id_outlet'));
        $request->filled('report_date') && $q->where('report_date', $request->input('report_date'));

        return view('reports.index', [
            'runs' => $q->paginate(20)->withQueryString(),
            'filters' => $request->only(['status', 'id_outlet', 'report_date']),
            'canSend' => $request->user()->can(Permissions::APPROVE_AND_SEND),
        ]);
    }

    public function show(Request $request, ReportRun $run): View
    {
        // Otorisasi baca per-outlet (OPS-1003).
        abort_unless($request->user()->canAccessOutlet((int) $run->id_outlet), 403);

        $run->load(['outlet', 'deliveries' => fn ($q) => $q->latest()]);

        return view('reports.show', [
            'run' => $run,
            // Tombol "Sudah saya kirim" hanya untuk Head Store outlet ini.
            'canSend' => $request->user()->can(Permissions::APPROVE_AND_SEND)
                && $request->user()->canAccessOutlet((int) $run->id_outlet),
        ]);
    }
}
