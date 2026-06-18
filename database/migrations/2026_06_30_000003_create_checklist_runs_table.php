<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-01 · instans harian checklist per outlet (System Design §4). Dibuat scheduler (M3-03), idempoten
 * per (outlet, template, tanggal). status open|complete|missed; score = skor kepatuhan (M3-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status')->default('open'); // open|complete|missed
            $table->decimal('score', 5, 2)->nullable(); // 0..100 (M3-04)
            $table->timestamps();

            $table->unique(['id_outlet', 'template_id', 'run_date']); // idempotensi scheduler
            $table->index(['id_outlet', 'run_date']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_runs');
    }
};
