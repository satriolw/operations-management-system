<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item checklist (M3-01). requires_photo → wajib bukti foto in-app (M3-02).
 */
class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'label', 'requires_photo', 'order'];

    protected $casts = ['requires_photo' => 'boolean', 'order' => 'integer'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }
}
