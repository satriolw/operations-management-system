<?php

namespace App\Modules\Ingestion;

use App\Modules\Ingestion\Auth\ConfigTokenProvider;
use App\Modules\Ingestion\Auth\NeviraTokenManager;
use App\Modules\Ingestion\Contracts\AccessTokenProvider;
use App\Modules\Ingestion\Contracts\TransactionSource;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Mengikat kontrak Ingestion ke implementasi konkret. Domain lain me-resolve
 * TransactionSource (interface), bukan NeviraApiSource — anti-corruption layer.
 * OPS-108 cukup mengganti binding AccessTokenProvider ke token manager.
 */
class IngestionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Token lifecycle login 24 jam (OPS-108) aktif bila service credential tersedia.
        // Tanpa service credential → fallback token statis (OPS-102, placeholder).
        $this->app->bind(AccessTokenProvider::class, function ($app) {
            $hasServiceCredential = ! empty(config('nevira.service_username'))
                && ! empty(config('nevira.service_password'));

            return $hasServiceCredential
                ? $app->make(NeviraTokenManager::class)
                : $app->make(ConfigTokenProvider::class);
        });

        $this->app->bind(TransactionSource::class, function ($app) {
            return new NeviraApiSource(
                $app->make(HttpFactory::class),
                $app->make(AccessTokenProvider::class),
            );
        });
    }
}
