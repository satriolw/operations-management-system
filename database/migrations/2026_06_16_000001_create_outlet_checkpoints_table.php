<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-803/OPS-105 · titik cek outlet-diam DINAMIS per outlet (config, bukan hardcode).
 * Dibaca OPS-502 saat runtime. Waktu cek presisi menit (WIB); ambang outlet-level (lihat outlets).
 * Antar jam cek wajib unik & berjarak >= 30 menit (validasi UI/Request).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->time('check_time'); // HH:MM (WIB)
            $table->timestamps();

            $table->unique(['id_outlet', 'check_time']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_checkpoints');
    }
};
