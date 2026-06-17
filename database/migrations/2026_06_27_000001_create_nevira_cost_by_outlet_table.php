<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1206 · atribusi biaya saldo NEVIRA per outlet (Epic L, System Design §3.15). Dari id_outlet
 * di history: count per aksi + total_cost per outlet/periode. flagged = burn abnormal (perlu
 * ditinjau, BUKAN tuduhan). Output ber-id_outlet + periode → query-able LBE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nevira_cost_by_outlet', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->string('period');          // label rentang, mis. 2026-06-01_2026-06-30
            $table->json('counts_json');        // count per aksi
            $table->bigInteger('total_cost');   // rupiah
            $table->boolean('flagged')->default(false); // burn abnormal → perlu ditinjau
            $table->timestamps();

            $table->unique(['id_outlet', 'period']);
            $table->index('period');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nevira_cost_by_outlet');
    }
};
