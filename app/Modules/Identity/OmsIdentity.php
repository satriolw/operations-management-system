<?php

namespace App\Modules\Identity;

/**
 * Identitas principal OMS yang ter-autentikasi — abstraksi netral-sumber (OPS-801).
 * Lokal sekarang (dari User+spatie); kelak dapat diisi dari SSO LBE/ERP tanpa ubah domain.
 *
 * CATATAN: ini identitas LOGIN OMS, bukan aktor NEVIRA (id_cashier/id_role). Jangan campur.
 */
final class OmsIdentity
{
    /**
     * @param  string[]  $roles
     * @param  string[]  $permissions
     */
    public function __construct(
        public readonly int|string $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly array $roles = [],
        public readonly array $permissions = [],
    ) {}

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
