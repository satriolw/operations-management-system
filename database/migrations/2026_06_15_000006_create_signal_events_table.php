<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · signal_events — sinyal operasional (outlet diam + anomali integritas).
 * ref_transaction_number = referensi NEVIRA; id_cashier = aktor NEVIRA (numerik), BUKAN user OMS,
 * BUKAN PII customer. payload_json hanya metadata sinyal (nominal, alasan, tanggal) — tanpa PII customer.
 * Indeks wajib: (id_outlet, type, detected_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            // SILENT_OUTLET|SELF_APPROVAL|BATCH_APPROVAL|ORPHANED_PRODUCTION|INPUT_ERROR_KPI|AGING_PIUTANG
            $table->string('type');
            $table->string('severity')->default('low'); // high (real-time) | low (digest) — OPS-1002
            $table->string('ref_transaction_number')->nullable(); // referensi NEVIRA
            $table->unsignedBigInteger('id_cashier')->nullable();  // aktor NEVIRA (bukan user OMS)
            $table->json('payload_json')->nullable();              // metadata sinyal (tanpa PII customer)
            $table->string('status')->default('OPEN');             // OPEN|REVIEWED|DISMISSED
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['id_outlet', 'type', 'detected_at']); // indeks wajib OPS-101
            $table->index('status');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_events');
    }
};
