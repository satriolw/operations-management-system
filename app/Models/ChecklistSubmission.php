<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Submission item checklist (M3-01). captured_at_server = stempel SERVER (anti-manipulasi).
 * photo_ref = pointer storage privat (watermark server-side M3-02).
 */
class ChecklistSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id', 'item_id', 'crew_user_id', 'photo_ref', 'captured_at_server', 'gps_lat', 'gps_lng', 'note',
    ];

    protected $casts = [
        'captured_at_server' => 'datetime',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'run_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'item_id');
    }
}
