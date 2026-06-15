<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Contracts\IdentityProvider;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Implementasi lokal IdentityProvider (OPS-801): identitas dari auth OMS + role/permission spatie.
 * Diganti oleh provider SSO (LBE/ERP) kelak tanpa menyentuh domain.
 */
final class LocalIdentityProvider implements IdentityProvider
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function current(): ?OmsIdentity
    {
        $user = $this->auth->guard()->user();
        if (! $user instanceof User) {
            return null;
        }

        return new OmsIdentity(
            id: $user->getAuthIdentifier(),
            name: (string) $user->name,
            email: $user->email,
            roles: $user->getRoleNames()->all(),
            permissions: $user->getAllPermissions()->pluck('name')->all(),
        );
    }

    public function check(): bool
    {
        return $this->auth->guard()->check();
    }

    public function can(string $permission): bool
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User && $user->can($permission);
    }
}
