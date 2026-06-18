<?php

/**
 * Parameter sinyal Nota Terlambat (Epic M, System Design §3.16). Enum status terminal NEVIRA
 * BELUM dikonfirmasi → daftar configurable; status di luar daftar diperlakukan NON-terminal
 * ("masih terbuka", perlu ditinjau). Ambang per-outlet di outlet_sla_config (OPS-1302).
 */
return [
    // Status yang dianggap SELESAI (dikecualikan dari deteksi terlambat). PERLU KONFIRMASI NEVIRA.
    'terminal_statuses' => ['DONE', 'TAKEN', 'SELESAI', 'COMPLETED', 'PICKED_UP'],
    // Dikecualikan: dibatalkan/refund.
    'excluded_statuses' => ['VOID', 'REFUND', 'CANCELLED'],

    // Default SLA outlet baru (OPS-1302). business_hours = ukur overdue dalam jam operasional.
    'defaults' => [
        'sla_clock_mode' => 'business_hours', // business_hours | wallclock
        'grace_minutes' => 30,
        'approaching_lead_minutes' => 120,    // H-x sebelum deadline → nudge
        'stuck_minutes_threshold' => 240,     // mandek di status > ini → "macet"
        'minor_overdue_minutes' => 120,       // ≤ ini = minor (digest); > = major (real-time)
    ],
];
