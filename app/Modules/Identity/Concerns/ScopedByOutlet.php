<?php

namespace App\Modules\Identity\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scoping per-outlet (OPS-1003). Trait untuk model output yang ber-id_outlet
 * (report_runs, signal_events, revenue_adjustments, dst). SEMUA query baca data
 * yang dipicu user internal WAJIB lewat ->visibleTo($user) agar tak bocor lintas outlet.
 *
 * - Admin → akses semua (tanpa filter).
 * - Staf ter-scope → hanya id_outlet yang di-assign.
 * - Tamu / tanpa assignment → tak ada baris (fail-closed).
 *
 * Catatan: ini untuk konteks USER (request). Job sistem (scheduler) berjalan
 * lintas-outlet tanpa konteks user → JANGAN pakai scope ini di job.
 */
trait ScopedByOutlet
{
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0'); // fail-closed: tamu tak lihat apa pun
        }

        if ($user->canAccessAllOutlets()) {
            return $query; // admin → semua
        }

        return $query->whereIn($this->getTable().'.id_outlet', $user->assignedOutletIds());
    }
}
