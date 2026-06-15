<?php

return [
    // Base URL NEVIRA REST API. Secret/host di .env — TIDAK di-commit.
    'base_url' => env('NEVIRA_BASE_URL'),

    // Bearer token (placeholder OPS-102). Lifecycle login 24 jam ditangani OPS-108
    // yang mengganti token provider tanpa menyentuh domain.
    'token' => env('NEVIRA_TOKEN'),

    // Service credential untuk re-auth (dipakai OPS-108), disimpan di secret store.
    'service_username' => env('NEVIRA_SERVICE_USERNAME'),
    'service_password' => env('NEVIRA_SERVICE_PASSWORD'),

    'timeout' => (int) env('NEVIRA_TIMEOUT', 30),

    'per_page' => (int) env('NEVIRA_PER_PAGE', 50),

    // Retry HTTP untuk error transient (429/5xx). Backoff dalam milidetik.
    // 401/403 BUKAN transient → tidak masuk jalur ini (diserahkan ke OPS-108).
    'retry' => [
        'times' => (int) env('NEVIRA_RETRY_TIMES', 3),
        'backoff_ms' => [1000, 3000, 9000],
    ],

    // Batas aman jumlah halaman paginasi (anti loop tak hingga).
    'max_pages' => (int) env('NEVIRA_MAX_PAGES', 1000),
];
