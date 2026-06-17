<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master data rantai approval (M2-01/02, System Design §4). Dipilih per band nominal + scope;
 * doc_type null = semua jenis. Tiap level: approver_role ATAU approver_user_id (≥1 wajib).
 */
class ApprovalChain extends Model
{
    use HasFactory;

    protected $fillable = [
        'doc_type', 'amount_band', 'scope', 'level', 'approver_role', 'approver_user_id',
    ];

    protected $casts = ['level' => 'integer'];

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
