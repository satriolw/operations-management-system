<?php

namespace App\Modules\Discipline\Http\Controllers;

use App\Models\LeaderboardSnapshot;
use App\Support\Time\Wib;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Tampilan leaderboard (M3-06). Rank GLOBAL; baris difilter per scope (OPS-1003) — Area Manager
 * hanya melihat outlet binaannya, admin/OM/HoO semua.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->input('period') ?: Wib::normalize(now())->format('Y-m');

        return view('discipline.leaderboard', [
            'period' => $period,
            'rows' => LeaderboardSnapshot::query()
                ->where('period', $period)
                ->visibleTo($request->user()) // scoping per-outlet
                ->with('outlet')
                ->orderBy('rank')
                ->get(),
        ]);
    }
}
