<?php

use App\Modules\Reporting\Scheduling\DailyReportScheduler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan laporan harian per outlet (OPS-104), timezone Asia/Jakarta.
// Guard: tabel outlets harus ada. DB bisa belum siap/tak terjangkau (sebelum migrate,
// build artifact, atau saat menjalankan artisan tanpa DB) → jangan menggagalkan console kernel.
try {
    if (Schema::hasTable('outlets')) {
        DailyReportScheduler::apply(app(\Illuminate\Console\Scheduling\Schedule::class));
    }
} catch (\Throwable $e) {
    // DB belum tersedia → lewati registrasi jadwal dinamis (mis. saat migrate pertama).
}

// Retensi payload mentah (OPS-705) — harian dini hari WIB.
Schedule::command('oms:purge-raw-payloads')->dailyAt('03:00')->timezone('Asia/Jakarta');

// Watchdog pengiriman (OPS-704) — cek setelah jendela kirim malam, sebelum purge.
Schedule::command('oms:watchdog-deliveries')->dailyAt('23:30')->timezone('Asia/Jakarta');

// Digest sinyal low (OPS-1002) — pagi WIB, bukan notifikasi per-kejadian.
Schedule::command('oms:signal-digest')->dailyAt('07:00')->timezone('Asia/Jakarta');

// Snapshot saldo merchant NEVIRA (OPS-1201, Epic L) — berkala utk burn/runway (OPS-1202).
Schedule::command('oms:capture-balance-snapshot')->everySixHours()->timezone('Asia/Jakarta');

// Sinyal saldo: alert runway (OPS-1204) + nudge pengajuan (OPS-1205) — pagi WIB, sebelum cutoff.
Schedule::command('oms:check-balance-signals')->dailyAt('08:00')->timezone('Asia/Jakarta');

// Atribusi biaya saldo per outlet (OPS-1206, P2) — bulanan, awal bulan utk bulan sebelumnya.
Schedule::command('oms:attribute-balance-cost')->monthlyOn(1, '04:00')->timezone('Asia/Jakarta');

// Retensi lampiran bukti dokumen keuangan (M2-06, selaras OPS-705) — bulanan dini hari.
Schedule::command('oms:purge-finance-attachments')->monthlyOn(1, '03:30')->timezone('Asia/Jakarta');

// Retensi foto checklist crew (M3-02, data sensitif) — bulanan dini hari.
Schedule::command('oms:purge-checklist-photos')->monthlyOn(1, '03:45')->timezone('Asia/Jakarta');

// Checklist harian (M3-03): buat run pagi, evaluasi deadline (reminder + eskalasi) berkala.
Schedule::command('oms:create-checklist-runs')->dailyAt('05:00')->timezone('Asia/Jakarta');
Schedule::command('oms:checklist-deadlines')->hourlyAt(5)->timezone('Asia/Jakarta');

// Skor kepatuhan checklist (M3-04) — akhir hari setelah jendela deadline.
Schedule::command('oms:score-checklists')->dailyAt('22:30')->timezone('Asia/Jakarta');

// Leaderboard ternormalisasi (M3-06) — harian (rata-rata bergerak meredam dorongan akhir periode).
Schedule::command('oms:build-leaderboard')->dailyAt('23:10')->timezone('Asia/Jakarta');

// Nota terlambat / macet (Epic M, OPS-1303) — adaptive polling (OPS-109): tick tiap 10 menit;
// PollScheduler menggerbang per outlet (buka sekarang + cadence efektif via watermark). Latensi
// turun dari ≤60 mnt jadi ~cadence (default 15 mnt), tanpa banjir API saat tutup.
Schedule::command('oms:check-late-orders')->everyTenMinutes()->timezone('Asia/Jakarta');

// Audit transaksi (Epic N, OPS-1402) — harian dini hari untuk hari sebelumnya (perlu ditinjau).
Schedule::command('oms:audit-transactions')->dailyAt('02:00')->timezone('Asia/Jakarta');

// Variance quantity → KPI input (Epic N, OPS-1405) — harian, agregat bulan berjalan.
Schedule::command('oms:score-qty-variance')->dailyAt('02:30')->timezone('Asia/Jakarta');
