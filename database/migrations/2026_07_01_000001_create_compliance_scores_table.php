<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-04 · skor kepatuhan checklist per outlet/periode (KPI Head Store, System Design §4). Agregat
 * dari run harian (score = % item selesai TEPAT WAKTU). Ber-id_outlet (scoping + LBE-ready).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->string('period'); // YYYY-MM
            $table->decimal('score', 5, 2)->default(0); // 0..100 rata-rata run
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('on_time_items')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamps();

            $table->unique(['id_outlet', 'period']);
            $table->index('period');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_scores');
    }
};
