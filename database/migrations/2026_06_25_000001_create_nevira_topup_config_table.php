<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1203 · master data kalender pencairan + parameter saldo NEVIRA (Epic L, System Design §3.15).
 * Singleton (tingkat-merchant, satu akun jaringan). Hari pencairan & ambang runway dapat diubah
 * tanpa deploy (aturan internal Finance bisa berubah) → logika cadence (OPS-1204/1205) menyesuaikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nevira_topup_config', function (Blueprint $table) {
            $table->id();
            // Hari pencairan (Carbon dayOfWeek: 0=Minggu..6=Sabtu). Default Senin(1) & Kamis(4).
            $table->json('disbursement_weekdays');
            $table->unsignedInteger('submission_cutoff_lead_hours')->default(24); // lead pengajuan sebelum cair
            $table->bigInteger('target_ceiling')->default(0);                      // target top-up (rupiah)
            $table->unsignedInteger('buffer_days')->default(3);                    // buffer di atas gap
            $table->unsignedInteger('warning_runway_days')->default(8);            // gap+buffer
            $table->unsignedInteger('critical_runway_days')->default(5);           // gap maksimum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nevira_topup_config');
    }
};
