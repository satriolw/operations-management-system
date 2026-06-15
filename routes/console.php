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
