<?php

/**
 * Kebijakan retensi data turunan (OPS-705, System Design §3.6).
 * Payload MENTAH (teks laporan ter-render, gambar, payload sinyal) dibersihkan setelah
 * N hari — angka turunan (total_sales, dll) & referensi (transaction_number) tetap untuk LBE.
 * N dapat dikonfigurasi via env.
 */
return [
    // Hapus payload_text + image_path report_runs setelah N hari.
    'report_payload_days' => (int) env('RETENTION_REPORT_PAYLOAD_DAYS', 90),

    // Kosongkan payload_json signal_events setelah N hari (metadata sinyal, bukan PII).
    'signal_payload_days' => (int) env('RETENTION_SIGNAL_PAYLOAD_DAYS', 180),
];
