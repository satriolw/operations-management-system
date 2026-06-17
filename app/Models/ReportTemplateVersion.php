<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versi template (OPS-1004). */
class ReportTemplateVersion extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    protected $fillable = ['report_template_id', 'version', 'layout_json', 'status', 'created_by', 'published_at'];

    protected $casts = [
        'layout_json' => 'array',
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }
}
