<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengiriman satu report_run ke satu channel/target. unique(report_run_id, channel)
 * + unique(idempotency_key) menegakkan "tepat satu channel aktif per target per hari".
 */
class ReportDelivery extends Model
{
    protected $fillable = [
        'report_run_id', 'id_outlet', 'channel', 'target',
        'status', 'error', 'sent_at', 'idempotency_key',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function reportRun(): BelongsTo
    {
        return $this->belongsTo(ReportRun::class);
    }
}
