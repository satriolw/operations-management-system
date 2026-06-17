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
];
