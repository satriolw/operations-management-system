<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instans harian checklist per outlet (M3-01). Ter-scope per outlet (OPS-1003). status open|
 * complete|missed; score = skor kepatuhan (M3-04).
 */
class ChecklistRun extends Model
{
    use HasFactory;
    use ScopedByOutlet;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_MISSED = 'missed';

    protected $fillable = ['id_outlet', 'template_id', 'run_date', 'status', 'score'];

    // run_date TANPA cast 'date': cast date menserialisasi ke datetime → memecah firstOrCreate/where
    // idempotensi (string '2026-06-18' vs '2026-06-18 00:00:00'). Simpan apa adanya (Y-m-d).
    protected $casts = ['score' => 'decimal:2'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ChecklistSubmission::class, 'run_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
