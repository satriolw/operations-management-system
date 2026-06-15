<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · revenue_adjustments — koreksi revenue dari VOID/REFUND (Penyesuaian Revenue, OPS-401/402).
 * Menyimpan referensi transaction_number (BUKAN salinan transaksi NEVIRA) + nilai turunan.
 * reason = alasan void/refund (operasional, diizinkan OPS-705) — bukan PII customer.
 * Semua tanggal dinormalkan WIB (OPS-103). Indeks wajib: restated_for_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->foreignId('report_run_id')->nullable()->constrained('report_runs')->nullOnDelete();
            $table->string('transaction_number');     // referensi NEVIRA
            $table->string('type');                    // VOID|REFUND
            $table->decimal('amount', 15, 2);          // nominal restate (= grand_total)
            $table->text('reason')->nullable();        // dari refund_notes/void_notes
            $table->date('nota_date');                 // tgl nota (created_at, WIB)
            $table->date('approved_at');               // approve_refund_void_date (WIB)
            $table->date('restated_for_date');         // tanggal nota yang di-restate
            $table->timestamps();

            $table->index('restated_for_date');        // indeks wajib OPS-101
            $table->index('transaction_number');
            $table->index(['id_outlet', 'restated_for_date']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_adjustments');
    }
};
