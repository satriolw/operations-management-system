<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot leaderboard per outlet/periode (M3-06). Ter-scope per outlet (OPS-1003): Area Manager
 * hanya melihat binaannya. rank dihitung GLOBAL; baris difilter per scope.
 */
class LeaderboardSnapshot extends Model
{
    use HasFactory;
    use ScopedByOutlet;

    protected $fillable = ['period', 'id_outlet', 'raw_score', 'score', 'rank', 'metric_breakdown_json'];

    protected $casts = [
        'raw_score' => 'decimal:2',
        'score' => 'decimal:2',
        'rank' => 'integer',
        'metric_breakdown_json' => 'array',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
