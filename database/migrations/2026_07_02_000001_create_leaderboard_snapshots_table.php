<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-06 · snapshot leaderboard per periode (System Design §6). score = skor ternormalisasi SETELAH
 * rata-rata bergerak (anti-gaming akhir periode); raw_score = komposit periode itu. Ber-id_outlet
 * (scoping + LBE-ready).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('period'); // YYYY-MM
            $table->unsignedBigInteger('id_outlet');
            $table->decimal('raw_score', 6, 2)->default(0);   // komposit periode (pra rata2 bergerak)
            $table->decimal('score', 6, 2)->default(0);        // smoothed → dasar ranking (anti-gaming)
            $table->unsignedInteger('rank')->default(0);
            $table->json('metric_breakdown_json')->nullable();
            $table->timestamps();

            $table->unique(['period', 'id_outlet']);
            $table->index(['period', 'rank']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
    }
};
