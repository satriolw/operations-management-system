<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · cashier_input_scores — KPI akurasi input per kasir (OPS-603).
 * id_cashier = aktor NEVIRA (pembuat nota), atribusi BUKAN ke refund_void_by. Output turunan agregat.
 * Tidak menyimpan PII customer. Unik per (outlet, cashier, periode).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_input_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->unsignedBigInteger('id_cashier'); // aktor NEVIRA
            $table->string('period');                  // mis. '2026-06' (bulan) atau rentang
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('txn_count')->default(0);
            $table->decimal('rate', 6, 4)->nullable(); // error_count / txn_count
            $table->timestamps();

            $table->unique(['id_outlet', 'id_cashier', 'period']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_input_scores');
    }
};
