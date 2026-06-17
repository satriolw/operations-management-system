<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2-01 · counter SEQ doc_number (System Design §3.2). Format:
 *   YYMMDD-{BRAND}{OUTLET2|HO}/{TYPE}/{DIV}/{SEQ3}
 * SEQ di-reset BULANAN (keputusan 17 Jun 2026) → unik per (brand, outlet_or_ho, doc_type, period=YYYY-MM).
 * outlet_or_ho = kode outlet 2-digit (Lampiran A) atau 'HO' untuk Head Office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('brand');         // LW|KWL
            $table->string('outlet_or_ho');  // kode 2-digit | HO
            $table->string('doc_type');
            $table->string('period');        // YYYY-MM (reset bulanan)
            $table->unsignedInteger('last_seq')->default(0);
            $table->timestamps();

            $table->unique(['brand', 'outlet_or_ho', 'doc_type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_number_sequences');
    }
};
