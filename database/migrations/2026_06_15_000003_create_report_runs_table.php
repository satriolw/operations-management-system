<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · report_runs — satu run laporan harian per (outlet, tanggal).
 * Menyimpan OUTPUT TURUNAN (angka agregat hasil hitung + teks render), bukan salinan
 * transaksi NEVIRA. payload_text/image_path dapat dipurge per kebijakan retensi (OPS-705).
 * unique(id_outlet, report_date) menegakkan idempotency per (outlet, report_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->date('report_date');
            $table->string('status')->default('pending'); // pending|generated|delivered|failed
            $table->longText('payload_text')->nullable();  // teks laporan ter-render (purgeable)
            $table->string('image_path')->nullable();       // path PNG dashboard (purgeable)
            $table->decimal('total_sales', 15, 2)->nullable();
            $table->decimal('realized', 15, 2)->nullable();   // terealisasi (paid)
            $table->decimal('receivable', 15, 2)->nullable(); // piutang (unpaid)
            $table->unsignedInteger('txn_count')->nullable();
            $table->timestamps();

            // Indeks wajib OPS-101; unique sekaligus menegakkan idempotency per hari.
            $table->unique(['id_outlet', 'report_date']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
