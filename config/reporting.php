<?php

/**
 * Konfigurasi laporan (OPS-903/OPS-1005).
 */
return [
    // Kapasitas 1 parameter approved Meta template (transport Opsi A). Konten melebihi → fallback hybrid.
    'meta_param_max' => (int) env('REPORTING_META_PARAM_MAX', 1024),

    // Periode laporan: hari kalender penuh (WIB), dikirim setelah hari ditutup (OPS-1005).
    'period' => env('REPORTING_PERIOD', 'calendar_day'),
    'cutoff_time' => env('REPORTING_CUTOFF', '23:59'),
];
