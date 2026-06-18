<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Skor kepatuhan checklist per outlet/periode (M3-04, KPI Head Store). Ter-scope per outlet (OPS-1003).
 */
class ComplianceScore extends Model
{
    use HasFactory;
    use ScopedByOutlet;

    protected $fillable = ['id_outlet', 'period', 'score', 'runs_count', 'on_time_items', 'total_items'];

    protected $casts = [
        'score' => 'decimal:2',
        'runs_count' => 'integer',
        'on_time_items' => 'integer',
        'total_items' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
