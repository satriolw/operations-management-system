<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu run laporan harian per (id_outlet, report_date). unique(id_outlet, report_date)
 * menegakkan idempotency (OPS-101 schema). Ber-id_outlet → ter-scope per outlet (OPS-1003).
 */
class ReportRun extends Model
{
    use ScopedByOutlet;

    protected $fillable = [
        'id_outlet', 'report_date', 'status', 'payload_text', 'image_path',
        'total_sales', 'realized', 'receivable', 'txn_count',
    ];

    // report_date disimpan sebagai 'Y-m-d' apa adanya (tanpa cast 'date' yang menambah
    // '00:00:00') agar idempotency lookup firstOrCreate(id_outlet, report_date) konsisten.
    protected $casts = [];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ReportDelivery::class);
    }
}
