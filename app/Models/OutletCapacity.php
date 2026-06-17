<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kapasitas outlet (OPS-1101, System Design §3.14). 1:1 outlet. Effective capacity (kg/jam)
 * diturunkan dari input dengan urutan: override → mesin×throughput → kg-hari÷jam-shift.
 * Dikonsumsi load model OPS-1103: utilisasi = kebutuhan kg/jam ÷ effective capacity.
 */
class OutletCapacity extends Model
{
    use HasFactory;

    protected $table = 'outlet_capacity';

    protected $fillable = [
        'id_outlet', 'kg_per_day', 'machines', 'shift_hours',
        'throughput_kg_per_machine_hour', 'capacity_kg_per_hour', 'overload_threshold_pct',
    ];

    protected $casts = [
        'kg_per_day' => 'float',
        'machines' => 'integer',
        'shift_hours' => 'float',
        'throughput_kg_per_machine_hour' => 'float',
        'capacity_kg_per_hour' => 'float',
        'overload_threshold_pct' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    /**
     * Effective capacity kg/jam. Override menang; lalu mesin×throughput; lalu kg-hari÷jam-shift.
     * Null bila input tak cukup (caller perlakukan sbg "kapasitas belum dikonfigurasi").
     */
    public function effectiveKgPerHour(): ?float
    {
        if ($this->capacity_kg_per_hour !== null && $this->capacity_kg_per_hour > 0) {
            return (float) $this->capacity_kg_per_hour; // override eksplisit
        }

        if ($this->machines && $this->throughput_kg_per_machine_hour) {
            return (float) $this->machines * (float) $this->throughput_kg_per_machine_hour;
        }

        if ($this->kg_per_day && $this->shift_hours && $this->shift_hours > 0) {
            return (float) $this->kg_per_day / (float) $this->shift_hours;
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->effectiveKgPerHour() !== null;
    }
}
