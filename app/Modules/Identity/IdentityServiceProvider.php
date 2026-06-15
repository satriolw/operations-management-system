<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\IdentityProvider;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat IdentityProvider ke implementasi lokal (OPS-801). Cukup ganti binding ke provider
 * SSO kelak. Aksi sensitif di-gate lewat permission spatie (auto-registered sebagai Gate::before),
 * jadi Gate::allows('deliver.approve_and_send') / $user->can(...) bekerja tanpa define manual.
 */
class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityProvider::class, function ($app) {
            return new LocalIdentityProvider($app->make(AuthFactory::class));
        });
    }
}
