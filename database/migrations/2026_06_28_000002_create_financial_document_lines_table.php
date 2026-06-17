<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · baris itemized dokumen (PR/RE/CA/ER). ER memakai `balance` (running balance realisasi CA).
 * Scope mengikuti dokumen induk (financial_documents.id_outlet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('financial_documents')->cascadeOnDelete();
            $table->string('description');
            $table->string('merk_type')->nullable();
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->nullable(); // ER: running balance (boleh negatif)
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_document_lines');
    }
};
