<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · outlets — konfigurasi outlet (output/registry, bukan salinan transaksi NEVIRA).
 *
 * PK = id_outlet (id outlet NEVIRA, natural key) → semua tabel turunan ber-id_outlet
 * agar mudah di-query LBE per outlet. Tidak menyimpan PII customer.
 * deliver_mode/wa_target sengaja TIDAK di sini — digantikan tabel delivery_targets
 * (System Design §3.3 v1.1, ditangani OPS-804), bukan scope OPS-101.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlets', function (Blueprint $table) {
            $table->unsignedBigInteger('id_outlet')->primary(); // id outlet NEVIRA
            $table->string('name');                              // nama outlet (bisnis, bukan PII customer)
            $table->time('report_time')->nullable();             // jam kirim laporan harian (WIB)
            $table->string('timezone')->default('Asia/Jakarta');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlets');
    }
};
