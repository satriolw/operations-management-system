<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · induk dokumen keuangan (Modul 2 Finance, System Design §3). SATU data model untuk 5 jenis
 * (PR/RE/CA/ER/REFUND); field spesifik di payload_json + lines. Ber-brand+id_outlet (scoping OPS-1003,
 * LBE-ready); dokumen Head Office → id_outlet null, scope HEAD_OFFICE. amount_band memilih rantai
 * approval (§4). parent_document_id: ER → CA yang direkonsiliasi. nevira_transaction_number: REFUND →
 * nota NEVIRA (REFERENSI saja, aturan emas: jangan salin kebenaran NEVIRA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_documents', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type'); // PAYMENT_REQUEST|REIMBURSE|CASH_ADVANCE|EXPENSE_REPORT|REFUND
            $table->string('doc_number')->nullable()->unique(); // di-generate (M2-04); unik bila ada
            $table->string('brand'); // LW|KWL
            $table->unsignedBigInteger('id_outlet')->nullable(); // null = Head Office
            $table->string('scope')->default('OUTLET'); // OUTLET|HEAD_OFFICE
            $table->unsignedBigInteger('requester_user_id');
            $table->string('title');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('amount_band')->nullable(); // LOW|HIGH (diturunkan dari amount)
            $table->string('cost_center')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->string('status')->default('DRAFT'); // DRAFT|SUBMITTED|APPROVED_L1|APPROVED_L2|FINAL|REJECTED
            $table->unsignedTinyInteger('current_level')->default(0);
            $table->unsignedBigInteger('parent_document_id')->nullable(); // ER → CA induk
            $table->string('nevira_transaction_number')->nullable();      // REFUND → nota NEVIRA (referensi)
            $table->json('payload_json')->nullable(); // field spesifik per jenis + payment info (PII terenkripsi di model, M2-06)
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['brand', 'id_outlet', 'doc_type', 'status']);
            $table->index('status');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->nullOnDelete();
            $table->foreign('requester_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_document_id')->references('id')->on('financial_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_documents');
    }
};
