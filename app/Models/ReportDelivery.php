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
    // Status hybrid (OPS-302): draft ke Head Store vs dikonfirmasi terkirim ke investor.
    public const AWAITING_CONFIRMATION = 'awaiting_confirmation'; // draft terkirim ke Head Store
    public const CONFIRMED_SENT = 'confirmed_sent';               // Head Store tekan "Sudah saya kirim"
    public const SENT = 'sent';                                  // Cloud API terkirim (Opsi A)
    public const FAILED = 'failed';
    // Status assisted (OPS-304): draft disiapkan, MENUNGGU Head Store tekan "Setujui & Kirim"
    // (app yang kirim via Cloud API). Berbeda dari hybrid (paste manual).
    public const AWAITING_APPROVAL = 'awaiting_approval';

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

    public function isAwaitingConfirmation(): bool
    {
        return $this->status === self::AWAITING_CONFIRMATION;
    }

    /** Draft assisted menunggu "Setujui & Kirim" Head Store (OPS-304). */
    public function isAwaitingApproval(): bool
    {
        return $this->status === self::AWAITING_APPROVAL;
    }

    /** Benar-benar terverifikasi sampai investor (dipakai watchdog OPS-704, bukan status draft). */
    public function isConfirmedDelivered(): bool
    {
        return in_array($this->status, [self::CONFIRMED_SENT, self::SENT], true);
    }
}
