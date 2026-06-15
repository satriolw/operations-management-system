<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-101 · report_deliveries — hasil pengiriman tiap report_run ke satu target/channel.
 * target = label/referensi tujuan (bukan PII customer). idempotency_key + unique(report_run, channel)
 * menegakkan "tepat satu channel aktif per target per hari" (System Design §3.5/§3.8).
 * id_outlet didenormalisasi agar tabel ber-id_outlet (LBE-ready).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_run_id')->constrained('report_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('id_outlet');
            $table->string('channel');                 // hybrid|assisted|full_auto
            $table->string('target')->nullable();      // label/referensi tujuan (bukan PII)
            $table->string('status')->default('pending'); // pending|sent|failed|confirmed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->unique('idempotency_key');
            $table->unique(['report_run_id', 'channel']);
            $table->index(['id_outlet', 'status']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_deliveries');
    }
};
