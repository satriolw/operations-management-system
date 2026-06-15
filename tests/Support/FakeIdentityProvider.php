<?php

namespace Tests\Support;

use App\Modules\Identity\Contracts\IdentityProvider;
use App\Modules\Identity\OmsIdentity;

/**
 * Provider identitas palsu untuk membuktikan IdentityProvider dapat ditukar (OPS-801)
 * — mensimulasikan sumber non-lokal (mis. SSO LBE/ERP) tanpa auth nyata.
 */
final class FakeIdentityProvider implements IdentityProvider
{
    public function __construct(private readonly ?OmsIdentity $identity) {}

    public function current(): ?OmsIdentity
    {
        return $this->identity;
    }

    public function check(): bool
    {
        return $this->identity !== null;
    }

    public function can(string $permission): bool
    {
        return $this->identity?->can($permission) ?? false;
    }
}
