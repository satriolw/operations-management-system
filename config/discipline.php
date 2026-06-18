<?php

/**
 * Parameter Modul 3 (Discipline). Anti-palsu: foto hanya via kamera in-app (capture token),
 * watermark + captured_at server-side. Foto = data sensitif crew (wajah/lokasi) → disk privat +
 * retensi. Bobot leaderboard (M3-05) configurable.
 */
return [
    // Capture-session token (M3-02): umur pendek; upload tanpa token sah = ditolak (anti galeri).
    'capture_token_ttl' => (int) env('DISCIPLINE_CAPTURE_TTL', 300), // detik

    // Penyimpanan foto: disk PRIVAT (bukan publik), akses ter-scope.
    'photo_disk' => env('DISCIPLINE_PHOTO_DISK', 'local'),
    'photo_dir' => 'discipline/photos',

    // Retensi foto crew (hari) — data sensitif; purge terjadwal (selaras OPS-705/M2-06).
    'photo_retention_days' => (int) env('DISCIPLINE_PHOTO_RETENTION_DAYS', 365),

    // Deadline checklist harian (M3-03), jam WIB: reminder lalu eskalasi bila belum lengkap.
    'reminder_hour' => (int) env('DISCIPLINE_REMINDER_HOUR', 12),
    'escalation_hour' => (int) env('DISCIPLINE_ESCALATION_HOUR', 20),

    // Bobot metrik leaderboard ternormalisasi (M3-05) — setara default, configurable.
    'leaderboard_weights' => [
        'growth' => (float) env('DISCIPLINE_W_GROWTH', 1),
        'revenue_per_capacity' => (float) env('DISCIPLINE_W_REVCAP', 1),
        'compliance' => (float) env('DISCIPLINE_W_COMPLIANCE', 1),
    ],
];
