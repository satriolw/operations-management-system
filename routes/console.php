<?php

use App\Modules\Reporting\Scheduling\DailyReportScheduler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Penjadwalan laporan harian per outlet (OPS-104), timezone Asia/Jakarta.
// Guard: tabel outlets harus ada (mis. sebelum migrate pertama / saat build artifact).
if (Schema::hasTable('outlets')) {
    DailyReportScheduler::apply(app(Schedule::class));
}
