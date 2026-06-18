<?php

namespace App\Modules\Signals\Http\Controllers;

use App\Models\ReviewLog;
use App\Models\SignalEvent;
use App\Modules\Signals\Exceptions\ReviewerIsSubject;
use App\Modules\Signals\Http\Requests\ReviewSignalRequest;
use App\Modules\Signals\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Tinjau sinyal (OPS-606). Catat jejak append-only + ubah status. Reviewer ≠ subjek → 403 (eskalasi).
 */
class SignalReviewController extends Controller
{
    /**
     * Layar triase sinyal (OPS-606). Ter-scope per-outlet (OPS-1003): staf hanya outlet binaan,
     * admin/OM/HoO semua. Default tampil yang OPEN dulu (severity tinggi di atas). payload hanya
     * metadata (tanpa PII) — sudah di-scrub saat detektor menyusunnya.
     */
    public function index(Request $request): View
    {
        $q = SignalEvent::query()
            ->visibleTo($request->user())   // scoping per-outlet (fail-closed)
            ->with('outlet')
            ->orderByRaw("CASE status WHEN 'OPEN' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
            ->latest('detected_at');

        foreach (['status', 'severity', 'type'] as $f) {
            $request->filled($f) && $q->where($f, $request->input($f));
        }
        $request->filled('id_outlet') && $q->where('id_outlet', (int) $request->input('id_outlet'));

        $signals = $q->paginate(25)->withQueryString();

        // Tinjauan terakhir per sinyal (append-only review_logs) untuk badge "sudah ditinjau".
        $lastReview = ReviewLog::where('subject_type', ReviewLog::SUBJECT_SIGNAL)
            ->whereIn('subject_id', $signals->pluck('id'))
            ->with('reviewer:id,name')
            ->orderBy('reviewed_at')
            ->get()->keyBy('subject_id'); // keyBy menyimpan yang TERAKHIR (urut menaik)

        return view('signals.index', [
            'signals' => $signals,
            'lastReview' => $lastReview,
            'filters' => $request->only(['status', 'severity', 'type', 'id_outlet']),
            'types' => SignalEvent::query()->visibleTo($request->user())->distinct()->orderBy('type')->pluck('type'),
            'canReview' => $request->user()->can(\App\Modules\Identity\Permissions::REVIEW_SIGNALS),
        ]);
    }

    public function review(ReviewSignalRequest $request, SignalEvent $signal, ReviewService $service): RedirectResponse
    {
        try {
            $service->reviewSignal(
                $signal, $request->user(),
                $request->input('outcome'), $request->input('note'), $request->input('evidence_path'),
            );
        } catch (ReviewerIsSubject $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('status', 'Tinjauan tercatat.');
    }
}
