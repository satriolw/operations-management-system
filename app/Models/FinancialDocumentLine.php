<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris itemized dokumen (M2-01). `balance` = running balance untuk Expense Report (boleh negatif).
 */
class FinancialDocumentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'description', 'merk_type', 'qty', 'unit_price', 'amount', 'balance', 'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(FinancialDocument::class, 'document_id');
    }
}
