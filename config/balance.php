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
];
