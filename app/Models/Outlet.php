<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Outlet (OPS-101 schema). PK = id_outlet (id NEVIRA, natural key, non-incrementing).
 */
class Outlet extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_outlet';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['id_outlet', 'name', 'report_time', 'timezone', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function reportRuns(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'id_outlet', 'id_outlet');
    }
}
