<?php

namespace App\Modules\Ingestion;

use App\Modules\Ingestion\Auth\ConfigTokenProvider;
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
        $this->app->bind(AccessTokenProvider::class, ConfigTokenProvider::class);

        $this->app->bind(TransactionSource::class, function ($app) {
            return new NeviraApiSource(
                $app->make(HttpFactory::class),
                $app->make(AccessTokenProvider::class),
            );
        });
    }
}
