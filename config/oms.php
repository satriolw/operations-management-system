<?php

return [
    // Store cache untuk metrik (OPS-701). null = cache default (Redis di prod, array saat test).
    'metrics_cache_store' => env('OMS_METRICS_CACHE_STORE'),
];
