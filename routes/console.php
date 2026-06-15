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
// Guard: tabel outlets harus ada (mis. sebelum migrate pertama / saat build artifact).
if (Schema::hasTable('outlets')) {
    DailyReportScheduler::apply(app(\Illuminate\Console\Scheduling\Schedule::class));
}

// Retensi payload mentah (OPS-705) — harian dini hari WIB.
Schedule::command('oms:purge-raw-payloads')->dailyAt('03:00')->timezone('Asia/Jakarta');
