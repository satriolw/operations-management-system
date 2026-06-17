<?php

namespace App\Models;

use App\Modules\Identity\Concerns\ScopedByOutlet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen keuangan (M2-01, Modul 2 Finance). SATU model untuk 5 jenis (PR/RE/CA/ER/REFUND);
 * field spesifik di payload_json + lines. Ber-brand+id_outlet → ScopedByOutlet (OPS-1003);
 * Head Office → id_outlet null, scope HEAD_OFFICE. amount_band memilih rantai approval (§4).
 */
class FinancialDocument extends Model
{
    use HasFactory;
    use ScopedByOutlet;

    public const TYPES = ['PAYMENT_REQUEST', 'REIMBURSE', 'CASH_ADVANCE', 'EXPENSE_REPORT', 'REFUND'];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED_L1 = 'APPROVED_L1';
    public const STATUS_APPROVED_L2 = 'APPROVED_L2';
    public const STATUS_FINAL = 'FINAL';
    public const STATUS_REJECTED = 'REJECTED';

    public const BAND_LOW = 'LOW';
    public const BAND_HIGH = 'HIGH';

    protected $fillable = [
        'doc_type', 'doc_number', 'brand', 'id_outlet', 'scope', 'requester_user_id', 'title',
        'amount', 'amount_band', 'cost_center', 'currency', 'status', 'current_level',
        'parent_document_id', 'nevira_transaction_number', 'payload_json', 'finalized_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'current_level' => 'integer',
        'payload_json' => 'array',
        'finalized_at' => 'datetime',
    ];

    /** Ambang band (System Design §4): < Rp1jt = LOW, ≥ Rp1jt = HIGH. */
    public const BAND_THRESHOLD = 1_000_000;

    public static function bandFor(float $amount): string
    {
        return $amount >= self::BAND_THRESHOLD ? self::BAND_HIGH : self::BAND_LOW;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinancialDocumentLine::class, 'document_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class, 'document_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class, 'document_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    /** ER → Cash Advance yang direkonsiliasi. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_document_id');
    }
}
