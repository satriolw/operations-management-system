<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Models\Outlet;
use App\Models\ReportRun;
use App\Models\SignalEvent;
use App\Support\Time\Wib;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Dashboard landing (OPS-806) — ringkas & role-aware, ter-scope per-outlet (OPS-1003). Angka NYATA
 * dari signal_events/report_runs (placeholder bila kosong → "Operasional bersih", bukan data palsu).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = Wib::normalize(now())->toDateString();

        $openSignals = SignalEvent::query()->visibleTo($user)->where('status', 'OPEN');
        $high = (clone $openSignals)->where('severity', 'high')->count();
        $low = (clone $openSignals)->where('severity', 'low')->count();

        $reportsToday = ReportRun::query()->visibleTo($user)->whereDate('report_date', $today);
        $reportsTotal = (clone $reportsToday)->count();
        $reportsDelivered = (clone $reportsToday)->where('status', 'delivered')->count();
        $reportsPending = max(0, $reportsTotal - $reportsDelivered);

        return view('dashboard', [
            'high' => $high,
            'low' => $low,
            'reportsTotal' => $reportsTotal,
            'reportsDelivered' => $reportsDelivered,
            'reportsPending' => $reportsPending,
            'outletsVisible' => $user->canAccessAllOutlets() ? Outlet::query()->where('active', true)->count() : count($user->assignedOutletIds()),
            // Nota terlambat (Epic M, OPS-1305): sinyal LATE_ORDER terbuka, ter-scope.
            'lateOrders' => (clone $openSignals)->where('type', 'LATE_ORDER')->count(),
            'clean' => ($high + $low + $reportsPending) === 0,
            'today' => $today,
        ]);
    }
}
