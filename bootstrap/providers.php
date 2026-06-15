<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Ingestion\IngestionServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IngestionServiceProvider::class,
    IdentityServiceProvider::class,
];
