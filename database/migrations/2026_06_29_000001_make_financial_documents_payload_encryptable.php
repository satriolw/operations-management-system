<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-06 · enkripsi-at-rest payload dokumen (System Design §6). payload_json memuat data sensitif
 * (rekening payee; untuk Refund: PII customer). Cast model → encrypted:array. Kolom diubah dari
 * `json` ke `longText` agar muat ciphertext (MySQL json menolak string non-JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_documents', function (Blueprint $table) {
            $table->longText('payload_json')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('financial_documents', function (Blueprint $table) {
            $table->json('payload_json')->nullable()->change();
        });
    }
};
