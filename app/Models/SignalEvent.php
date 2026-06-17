<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sinyal operasional (OPS-101 schema). payload_json HANYA metadata sinyal (tanpa PII customer);
 * gunakan PiiPolicy::scrubSignalPayload() saat menyusunnya. Ter-scope per outlet (OPS-1003).
 */
class SignalEvent extends Model
{
    use ScopedByOutlet;

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

    /**
     * Aktor NEVIRA yang menjadi SUBJEK sinyal (OPS-606 reviewer ≠ subjek):
     * pembuat nota + pemohon/penyetuju (bila ada di payload).
     *
     * @return array<int,int>
     */
    public function subjectActorIds(): array
    {
        $ids = [$this->id_cashier, $this->payload_json['approved_by'] ?? null, $this->payload_json['requested_by'] ?? null];

        return collect($ids)->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
    }
}

