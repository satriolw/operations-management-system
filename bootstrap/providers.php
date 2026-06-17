<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Ingestion\IngestionServiceProvider;
use App\Modules\Reporting\ReportingServiceProvider;
use App\Modules\Signals\SignalsServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IngestionServiceProvider::class,
    IdentityServiceProvider::class,
    ReportingServiceProvider::class,
    SignalsServiceProvider::class,
];
