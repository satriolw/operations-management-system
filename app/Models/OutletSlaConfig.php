<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SLA produksi per outlet (OPS-1302, Epic M). Default aman bila outlet belum dikonfigurasi.
 */
class OutletSlaConfig extends Model
{
    use HasFactory;

    protected $table = 'outlet_sla_config';

    protected $fillable = [
        'id_outlet', 'sla_clock_mode', 'grace_minutes', 'approaching_lead_minutes',
        'stuck_minutes_threshold', 'minor_overdue_minutes',
    ];

    protected $casts = [
        'grace_minutes' => 'integer',
        'approaching_lead_minutes' => 'integer',
        'stuck_minutes_threshold' => 'integer',
        'minor_overdue_minutes' => 'integer',
    ];

    /** Config outlet, atau instance default (tak tersimpan) bila belum diset. */
    public static function forOutlet(int $idOutlet): self
    {
        return static::query()->firstWhere('id_outlet', $idOutlet)
            ?? (new self(['id_outlet' => $idOutlet] + (array) config('late_orders.defaults', [])));
    }
}
