<?php

/**
 * Parameter Modul 2 (Finance). doc_number §3.2: YYMMDD-{BRAND}{OUTLET2|HO}/{TYPE}/{DIV}/{SEQ3}.
 * Kode outlet Modul 2 ≠ id_outlet NEVIRA → peta `outlet_codes` (master, isi via Ops/Admin).
 */
return [
    'division' => env('FINANCE_DIVISION', 'OPS'),

    // Kode jenis dokumen untuk doc_number.
    'type_codes' => [
        'PAYMENT_REQUEST' => 'PR',
        'REIMBURSE' => 'RE',
        'CASH_ADVANCE' => 'CA',
        'EXPENSE_REPORT' => 'ER',
        'REFUND' => 'RF',
    ],

    // Peta id_outlet (NEVIRA) → kode 2-digit Lampiran A. Diisi Ops (master data). Kosong → fallback.
    // Contoh: 120 (Fatmawati) => '06', 121 (Pondok Indah) => '07'.
    'outlet_codes' => [],

    // Lampiran bukti (M2-06, §6). Disk PRIVAT (bukan publik); akses ter-scope per outlet/role.
    'attachment_disk' => env('FINANCE_ATTACHMENT_DISK', 'local'),
    'attachment_dir' => 'finance/attachments',
    // Retensi lampiran (hari). Dokumen keuangan disimpan lama (default 5 tahun); purge selaras OPS-705.
    'attachment_retention_days' => (int) env('FINANCE_ATTACHMENT_RETENTION_DAYS', 1825),
];
