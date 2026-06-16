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

    protected $fillable = [
        'id_outlet', 'name', 'report_time', 'timezone', 'active',
        'silent_threshold_pct', 'comparison_basis',
    ];

    protected $casts = [
        'active' => 'boolean',
        'silent_threshold_pct' => 'integer',
    ];

    public function reportRuns(): HasMany
    {
        return $this->hasMany(ReportRun::class, 'id_outlet', 'id_outlet');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(OutletCheckpoint::class, 'id_outlet', 'id_outlet');
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OutletOperatingHour::class, 'id_outlet', 'id_outlet');
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(OutletHoliday::class, 'id_outlet', 'id_outlet');
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(OutletBaseline::class, 'id_outlet', 'id_outlet');
    }

    /** OPS-803/OPS-501: outlet baru belum punya baseline → UI beri catatan. */
    public function hasBaseline(): bool
    {
        return $this->baselines()->exists();
    }
}
