<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Counter SEQ doc_number (M2-01, §3.2). Reset bulanan: unik per (brand, outlet_or_ho, doc_type, period).
 * Generator atomik (M2-04) memakai baris ini.
 */
class DocNumberSequence extends Model
{
    protected $fillable = ['brand', 'outlet_or_ho', 'doc_type', 'period', 'last_seq'];

    protected $casts = ['last_seq' => 'integer'];
}
