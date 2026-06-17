<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · jejak approval dokumen (APPEND-ONLY, pola audit OPS-606). Tiap aksi approve/reject
 * satu baris: siapa (approver_user_id + role), kapan (acted_at), outcome, catatan. Tidak diupdate/
 * dihapus (ditegakkan di model M2-03). reviewer ≠ requester ditegakkan engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('financial_documents')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->unsignedBigInteger('approver_user_id');
            $table->string('approver_role')->nullable(); // role efektif saat menyetujui
            $table->string('action'); // APPROVED|REJECTED
            $table->text('note')->nullable(); // wajib saat REJECTED (ditegakkan engine)
            $table->timestamp('acted_at');
            $table->timestamps();

            $table->index(['document_id', 'level']);
            $table->foreign('approver_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};
