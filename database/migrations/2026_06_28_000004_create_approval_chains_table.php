<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · master data rantai approval (System Design §4). Rantai dipilih per BAND nominal (LOW/HIGH)
 * + scope; doc_type null = berlaku semua jenis. Tiap level diisi approver_role ATAU approver_user_id
 * (≥1 wajib — ditegakkan CRUD M2-02). Default: LOW = AM→OM, HIGH = OM→HoO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_chains', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type')->nullable(); // null = semua jenis
            $table->string('amount_band'); // LOW|HIGH
            $table->string('scope')->default('OUTLET'); // OUTLET|HEAD_OFFICE
            $table->unsignedTinyInteger('level'); // 1, 2, ...
            $table->string('approver_role')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->timestamps();

            $table->index(['doc_type', 'amount_band', 'scope', 'level']);
            $table->foreign('approver_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chains');
    }
};
