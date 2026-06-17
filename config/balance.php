<?php

/**
 * Parameter saldo & cost NEVIRA (Epic L). Unit cost per aksi memotong saldo deposit merchant.
 * ⚠️ export_laporan BELUM dikonfirmasi NEVIRA → null (jangan asumsikan). Ambang runway/pencairan
 * = master data configurable (OPS-1203, nevira_topup_config), bukan di sini.
 */
return [
    // Biaya per aksi (rupiah). Dipakai atribusi biaya (OPS-1206) & fallback burn bila delta saldo minim.
    'unit_cost' => [
        'nota_transaksi' => (int) env('NEVIRA_COST_NOTA', 100),
        'cetak_struk' => (int) env('NEVIRA_COST_STRUK', 50),
        'kirim_whatsapp' => (int) env('NEVIRA_COST_WA', 75),
        'export_laporan' => env('NEVIRA_COST_EXPORT'), // null = belum dikonfirmasi
    ],

    // Anomali burn per outlet (OPS-1206): flag bila total_cost > factor × median populasi,
    // hanya bila populasi ≥ min_outlets. "Perlu ditinjau", bukan tuduhan.
    'anomaly_factor' => (float) env('NEVIRA_COST_ANOMALY_FACTOR', 3.0),
    'anomaly_min_outlets' => (int) env('NEVIRA_COST_ANOMALY_MIN_OUTLETS', 3),
];
