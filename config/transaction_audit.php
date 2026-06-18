<?php

/**
 * Parameter audit transaksi & anomali (Epic N, System Design §3.17). VERIFICATION-GATED: semantik
 * beberapa field NEVIRA belum dikonfirmasi → `review_mode=true` ⇒ sinyal berlabel "perlu ditinjau"
 * (flag, BUKAN tuduhan, tanpa auto-aksi). Ambang per-outlet di transaction_audit_config (CRUD).
 */
return [
    // Gerbang verifikasi global. Tetap true sampai semantik field NEVIRA dikonfirmasi.
    'review_mode' => (bool) env('AUDIT_REVIEW_MODE', true),

    // Promo resmi yang DIKECUALIKAN dari agregasi kebocoran (nama persis dari promos[].name).
    'promo_whitelist' => [],

    // Pembayaran (OPS-1403): metode non-tunai (change_amount seharusnya 0 — PERLU KONFIRMASI QRIS/DEPOSIT)
    // & metode yang wajib bukti (payment_proof). Semantik belum dikonfirmasi → review_mode.
    'cashless_methods' => ['QRIS', 'DEPOSIT', 'TRANSFER', 'DEBIT', 'CREDIT', 'EDC'],
    'proof_required_methods' => ['TRANSFER'],

    // Off-price (OPS-1404): grup pelanggan B2B resmi yang DIKECUALIKAN (id_customer_group). PERLU KONFIRMASI.
    'b2b_customer_groups' => [],

    // Default ambang outlet baru (transaction_audit_config OPS-1402..1406).
    'defaults' => [
        'promo_leak_pct' => 15.0,         // diskon > % omzet/hari → flag
        'promo_leak_daily_cap' => 500000, // atau diskon > Rp/hari → flag
        'payment_anomaly_min_amount' => 50000,
        'offprice_tolerance_pct' => 5.0,
        'qty_variance_pct' => 20.0,
        'deposit_expiry_lead_days' => 14,
    ],
];
