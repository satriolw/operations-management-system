<?php

namespace App\Modules\Signals\Http\Controllers;

use App\Models\SignalEvent;
use App\Modules\Signals\Exceptions\ReviewerIsSubject;
use App\Modules\Signals\Http\Requests\ReviewSignalRequest;
use App\Modules\Signals\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * Tinjau sinyal (OPS-606). Catat jejak append-only + ubah status. Reviewer ≠ subjek → 403 (eskalasi).
 */
class SignalReviewController extends Controller
{
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
