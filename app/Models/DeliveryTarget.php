<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target pengiriman per outlet→investor (OPS-804). Mode per target; assisted/full_auto
 * hanya bila akun OBA siap (gerbang OPS-306).
 */
class DeliveryTarget extends Model
{
    use HasFactory;

    public const MODES = ['hybrid', 'assisted', 'full_auto'];

    protected $fillable = [
        'id_outlet', 'investor_label', 'channel_type', 'whatsapp_account_id',
        'group_id', 'group_ready', 'deliver_mode', 'template_label', 'active',
    ];

    protected $casts = [
        'group_ready' => 'boolean',
        'active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappAccount::class);
    }

    public function modeRequiresOba(string $mode): bool
    {
        return in_array($mode, ['assisted', 'full_auto'], true);
    }

    /** Mode efektif untuk tampilan: fallback ke hybrid bila akun OBA tak siap (nomor lost dll). */
    public function effectiveMode(): string
    {
        if ($this->modeRequiresOba($this->deliver_mode)
            && ! ($this->whatsappAccount?->obaReady() ?? false)) {
            return 'hybrid';
        }

        return $this->deliver_mode;
    }

    public function isFallback(): bool
    {
        return $this->effectiveMode() !== $this->deliver_mode;
    }
}
