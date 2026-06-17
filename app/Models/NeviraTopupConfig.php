<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi pencairan & ambang saldo NEVIRA (OPS-1203, Epic L). Singleton tingkat-merchant.
 * Configurable tanpa deploy; dikonsumsi alert runway (OPS-1204) & nudge kadens (OPS-1205).
 */
class NeviraTopupConfig extends Model
{
    use HasFactory;

    protected $table = 'nevira_topup_config';

    protected $fillable = [
        'disbursement_weekdays', 'submission_cutoff_lead_hours', 'target_ceiling',
        'buffer_days', 'warning_runway_days', 'critical_runway_days',
    ];

    protected $casts = [
        'disbursement_weekdays' => 'array',
        'submission_cutoff_lead_hours' => 'integer',
        'target_ceiling' => 'integer',
        'buffer_days' => 'integer',
        'warning_runway_days' => 'integer',
        'critical_runway_days' => 'integer',
    ];

    /** Singleton: satu baris config (default Senin/Kamis bila belum diset). */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'disbursement_weekdays' => [1, 4], // Senin, Kamis
            'submission_cutoff_lead_hours' => 24,
            'target_ceiling' => 0,
            'buffer_days' => 3,
            'warning_runway_days' => 8,
            'critical_runway_days' => 5,
        ]);
    }

    /** @return array<int,int> hari pencairan (dayOfWeek), terurut & valid 0..6 */
    public function weekdays(): array
    {
        return collect($this->disbursement_weekdays ?? [])
            ->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 0 && $d <= 6)->unique()->sort()->values()->all();
    }
}
