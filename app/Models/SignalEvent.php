<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sinyal operasional (OPS-101 schema). payload_json HANYA metadata sinyal (tanpa PII customer);
 * gunakan PiiPolicy::scrubSignalPayload() saat menyusunnya.
 */
class SignalEvent extends Model
{
    protected $fillable = [
        'id_outlet', 'type', 'severity', 'ref_transaction_number',
        'id_cashier', 'payload_json', 'status', 'detected_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'detected_at' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }
}
