<?php

return [
    /*
     * WhatsApp Cloud API (mode assisted/full_auto, OPS-303). MASTER SWITCH: default OFF → CloudApiDeliverer
     * menolak (DeliveryFailed) sehingga DeliveryDispatcher fallback ke hybrid. Go-live OBA = set
     * WHATSAPP_ENABLED=true + isi kredensial; TIDAK perlu ubah kode (semua jalur sudah dites sandbox).
     */
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),

    // Endpoint Graph API. base_url + version + phone_number_id menyusun URL kirim.
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    // Approved Meta template fleksibel (1 parameter body besar) — model konten dipisah dari transport
    // (System Design §3.9). Konten render layout_json diisi ke parameter tunggal ini.
    'template' => [
        'name' => env('WHATSAPP_TEMPLATE_NAME', 'laporan_harian'),
        'language' => env('WHATSAPP_TEMPLATE_LANG', 'id'),
        // Batas aman parameter body approved template. Konten melebihi ini → tak muat → fallback hybrid.
        'max_param_chars' => (int) env('WHATSAPP_TEMPLATE_MAX_CHARS', 1024),
    ],

    'timeout' => (int) env('WHATSAPP_TIMEOUT', 20),

    /*
     * Resolusi kredensial: whatsapp_accounts menyimpan credentials_ref (REFERENSI), bukan token mentah
     * (aturan emas #7). Peta ref→token diambil dari secret store / env, TIDAK di-commit & tak pernah dilog.
     * Map opsional di sini hanya untuk dev/sandbox via env JSON; prod pakai secret store nyata.
     */
    'credentials' => array_filter([
        // contoh: 'secret://wa/lw-utama' => env('WHATSAPP_TOKEN_LW_UTAMA')
    ]),

    // Fallback token tunggal (sandbox / satu nomor) bila credentials_ref tak terpetakan.
    'default_token' => env('WHATSAPP_DEFAULT_TOKEN'),
];
