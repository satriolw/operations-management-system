<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-803/OPS-105 · titik cek outlet-diam DINAMIS per outlet (config, bukan hardcode).
 * Dibaca OPS-502 saat runtime. ambang (threshold_pct) per titik cek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->unsignedTinyInteger('checkpoint_hour');          // 0-23 (WIB)
            $table->unsignedTinyInteger('threshold_pct')->default(50); // ambang % vs baseline
            $table->timestamps();

            $table->unique(['id_outlet', 'checkpoint_hour']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_checkpoints');
    }
};
