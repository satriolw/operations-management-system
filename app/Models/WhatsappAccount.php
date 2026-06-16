<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun WhatsApp pengirim (OPS-804). credentials_ref disembunyikan dari serialisasi (aturan emas #7).
 */
class WhatsappAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'label', 'id_outlet', 'phone_number', 'provider',
        'oba_status', 'account_status', 'credentials_ref', 'active',
    ];

    protected $hidden = ['credentials_ref'];

    protected $casts = ['active' => 'boolean'];

    public function targets(): HasMany
    {
        return $this->hasMany(DeliveryTarget::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'id_outlet');
    }

    /** OBA dapat dipakai (gerbang assisted/full_auto): OBA aktif & akun tidak lost. */
    public function obaReady(): bool
    {
        return $this->oba_status === 'active' && $this->account_status !== 'lost';
    }

    public function isLost(): bool
    {
        return $this->account_status === 'lost';
    }

    /** Nomor ter-mask utk tampilan (jangan bocorkan penuh di UI). */
    public function maskedPhone(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone_number);
        if (strlen($digits) < 6) {
            return '••••';
        }
        $tail = substr($digits, -4);
        $head = substr($digits, 0, strlen($digits) - 4 - 3);

        return '+'.$head.'-•••-'.$tail;
    }
}
