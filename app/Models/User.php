<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * User LOGIN OMS (identitas aplikasi) — siapa boleh "Setujui & Kirim", review sinyal,
 * edit master data. BUKAN aktor NEVIRA: id_cashier/id_role ada pada transaksi/signal_events
 * (lihat TransactionDTO, SignalEvent) dan TIDAK dicampur ke sini (System Design §3.10).
 */
#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Outlet yang di-assign (scoping, OPS-802/OPS-1003). Admin = semua (tanpa baris). */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'user_outlet', 'user_id', 'id_outlet');
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // --- Scoping per-outlet (OPS-1003) ---

    /** Admin = akses semua outlet (tanpa assignment). */
    public function canAccessAllOutlets(): bool
    {
        return $this->hasRole(\App\Modules\Identity\Permissions::ROLE_ADMIN);
    }

    /** @return array<int,int> id_outlet yang di-assign ke user ini. */
    public function assignedOutletIds(): array
    {
        return $this->outlets()->pluck('outlets.id_outlet')->map(fn ($id) => (int) $id)->all();
    }

    /** Boleh akses data outlet tertentu? Admin → selalu; lainnya → hanya yang di-assign. */
    public function canAccessOutlet(int $idOutlet): bool
    {
        return $this->canAccessAllOutlets() || in_array($idOutlet, $this->assignedOutletIds(), true);
    }
}
