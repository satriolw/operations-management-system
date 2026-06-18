<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master template checklist (M3-01, Modul 3 Discipline). id_outlet null = grup (semua outlet).
 */
class ChecklistTemplate extends Model
{
    use HasFactory;

    public const SCHEDULES = ['daily', 'shift'];

    protected $fillable = ['id_outlet', 'name', 'schedule', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'template_id')->orderBy('order');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
