<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Jejak approval dokumen (M2-01, pola audit OPS-606). APPEND-ONLY: tak boleh diubah/dihapus.
 */
class DocumentApproval extends Model
{
    use HasFactory;

    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    protected $fillable = [
        'document_id', 'level', 'approver_user_id', 'approver_role', 'action', 'note', 'acted_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'acted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('document_approvals append-only: tak dapat diubah.'));
        static::deleting(fn () => throw new RuntimeException('document_approvals append-only: tak dapat dihapus.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FinancialDocument::class, 'document_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
