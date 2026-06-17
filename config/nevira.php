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

    // Saldo deposit tingkat-merchant (Epic L). Satu akun seluruh jaringan (id_merchant 69).
    'merchant_id' => env('NEVIRA_MERCHANT_ID', 69),

    // --- Token lifecycle login 24 jam (OPS-108) ---
    'login_path' => env('NEVIRA_LOGIN_PATH', '/api/login'),
    'auth' => [
        'cache_key' => env('NEVIRA_TOKEN_CACHE_KEY', 'nevira:access_token'),
        // null = pakai cache default (Redis di prod, array saat test). Shared antar worker.
        'cache_store' => env('NEVIRA_TOKEN_CACHE_STORE'),
        'lifetime_hours' => (int) env('NEVIRA_TOKEN_LIFETIME_HOURS', 24),
        // refresh proaktif bila umur token mendekati 24 jam
        'refresh_after_hours' => (int) env('NEVIRA_TOKEN_REFRESH_AFTER_HOURS', 23),
        // single-flight lock: berapa lama lock dipegang & berapa lama worker lain menunggu
        'lock_seconds' => (int) env('NEVIRA_LOGIN_LOCK_SECONDS', 15),
        'lock_wait_seconds' => (int) env('NEVIRA_LOGIN_LOCK_WAIT_SECONDS', 15),
    ],

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

    // Backlog order berjalan (OPS-1102). Param filter server "belum selesai" BELUM dikonfirmasi
    // NEVIRA → default kosong + guard sisi-klien (completion_date null). Isi saat param resmi ada.
    'active_orders_params' => [],
];
