<?php

/**
 * Konfigurasi sinyal anomali (Epic F). Ambang dapat diatur tanpa deploy.
 */
return [
    // OPS-602: > N persetujuan oleh approver sama dalam jendela menit yang sama → batch-approval.
    'batch_threshold' => (int) env('SIGNALS_BATCH_THRESHOLD', 2),

    // OPS-605: piutang (UNPAID) melewati N hari → aging.
    'aging_days' => (int) env('SIGNALS_AGING_DAYS', 14),

    // OPS-603: kategori alasan yang dihitung sbg "salah input" (KPI kasir).
    'input_error_keywords' => ['salah input', 'salah masuk', 'salah nota', 'salah outlet', 'double input', 'terinput', 'input nota'],
];
