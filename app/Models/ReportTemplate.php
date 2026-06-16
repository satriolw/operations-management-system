<?php

namespace App\Models;

use App\Modules\Templating\TemplateTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template laporan (OPS-901). Master → override per outlet via parent_template_id (System Design §3.9).
 */
class ReportTemplate extends Model
{
    public const SCOPE_MASTER = 'master';
    public const SCOPE_OUTLET = 'outlet';
    public const SCOPE_TARGET = 'target';

    protected $fillable = [
        'scope', 'parent_template_id', 'id_outlet', 'name',
        'layout_json', 'meta_template_ref', 'active', 'updated_by',
    ];

    protected $casts = [
        'layout_json' => 'array',
        'active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_template_id');
    }

    public function tokens(): array
    {
        return TemplateTokens::extract($this->layout_json ?? []);
    }

    public function hasValidTokens(): bool
    {
        return TemplateTokens::isValid($this->layout_json ?? []);
    }
}
