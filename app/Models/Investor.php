<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master investor ringan (OPS-1005), 1:1 outlet. Tidak login app. wa_contact = referensi
 * kontak (re-invite/CRM), bukan PII customer.
 */
class Investor extends Model
{
    protected $fillable = ['name', 'wa_contact', 'id_outlet', 'since_date', 'notes', 'active'];

    protected $casts = [
        'since_date' => 'date',
        'active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function deliveryTargets(): HasMany
    {
        return $this->hasMany(DeliveryTarget::class);
    }
}
