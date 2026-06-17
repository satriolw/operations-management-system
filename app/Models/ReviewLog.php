<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Jejak tinjauan (OPS-606). APPEND-ONLY: tak boleh diubah/dihapus diam-diam.
 */
class ReviewLog extends Model
{
    public const SUBJECT_SIGNAL = 'signal';
    public const SUBJECT_REVENUE = 'revenue_adjustment';

    protected $fillable = [
        'subject_type', 'subject_id', 'reviewer_user_id', 'outcome', 'note', 'evidence_path', 'reviewed_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('review_logs append-only: tak dapat diubah.'));
        static::deleting(fn () => throw new RuntimeException('review_logs append-only: tak dapat dihapus.'));
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
