<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-606 · audit trail tinjauan (evidence). Append-only: siapa (reviewer_user_id), kapan
 * (reviewed_at), outcome, catatan (wajib), lampiran opsional. Membuktikan "sudah ditinjau".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // signal | revenue_adjustment
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('outcome');      // wajar | ditindaklanjuti | eskalasi
            $table->text('note');           // WAJIB
            $table->string('evidence_path')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_logs');
    }
};
