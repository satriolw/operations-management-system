<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-803/OPS-106 · jam operasional per outlet (WIB) sbg DAFTAR JENDELA per weekday
 * (dukung split-shift). Weekday tanpa jendela = tutup. Dipakai meredam false alarm
 * outlet-diam (OPS-501/502) & empty-state laporan (OPS-1001). Jendela tak boleh tumpang tindih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->unsignedTinyInteger('weekday'); // 0=Minggu .. 6=Sabtu
            $table->time('open_time');
            $table->time('close_time');
            $table->timestamps();

            $table->index(['id_outlet', 'weekday']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_operating_hours');
    }
};
