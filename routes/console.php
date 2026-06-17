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
