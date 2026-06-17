<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · lampiran bukti dokumen (receipt/lainnya). file_ref = pointer ke storage TERKONTROL
 * (bukan publik); akses ter-scope per outlet/role + retensi (M2-06, selaras OPS-705). Tanpa
 * menyimpan blob/secret di kolom biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('financial_documents')->cascadeOnDelete();
            $table->string('file_ref');          // path di disk privat (bukan URL publik)
            $table->string('kind')->default('receipt'); // receipt|other
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
