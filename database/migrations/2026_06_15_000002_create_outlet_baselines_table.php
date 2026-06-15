<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · outlet_baselines — baseline jumlah transaksi per outlet per titik cek
 * (dipakai deteksi outlet diam, OPS-501). Output turunan; dihitung dari hari buka
 * & bertransaksi saja (anti-bias, ditangani OPS-501).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_baselines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->unsignedTinyInteger('checkpoint_hour'); // 0-23 (WIB)
            $table->decimal('avg_txn', 10, 2)->default(0);  // rata-rata jumlah transaksi
            $table->unsignedInteger('sample_days')->default(0);
            $table->timestamps();

            $table->unique(['id_outlet', 'checkpoint_hour']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_baselines');
    }
};
