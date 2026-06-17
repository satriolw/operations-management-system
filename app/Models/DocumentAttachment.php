<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran bukti dokumen (M2-01/06). file_ref = pointer storage terkontrol (bukan publik).
 */
class DocumentAttachment extends Model
{
    use HasFactory;

    public const KIND_RECEIPT = 'receipt';
    public const KIND_OTHER = 'other';

    protected $fillable = ['document_id', 'file_ref', 'kind', 'original_name', 'uploaded_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(FinancialDocument::class, 'document_id');
    }
}
