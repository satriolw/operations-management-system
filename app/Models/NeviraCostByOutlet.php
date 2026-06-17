<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biaya saldo NEVIRA per outlet/periode (OPS-1206, Epic L). flagged = burn abnormal (perlu
 * ditinjau, bukan tuduhan). Query-able LBE (ber-id_outlet + periode).
 */
class NeviraCostByOutlet extends Model
{
    use HasFactory;

    protected $table = 'nevira_cost_by_outlet';

    protected $fillable = ['id_outlet', 'period', 'counts_json', 'total_cost', 'flagged'];

    protected $casts = [
        'counts_json' => 'array',
        'total_cost' => 'integer',
        'flagged' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
