<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-01 · submission item checklist (System Design §3/§5). captured_at_server = stempel SERVER
 * (anti-manipulasi, BUKAN dari klien). photo_ref = storage privat (watermark server-side M3-02).
 * gps_lat/lng opsional (tanpa enforce radius v1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('checklist_runs')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('checklist_items')->cascadeOnDelete();
            $table->unsignedBigInteger('crew_user_id');
            $table->string('photo_ref')->nullable();          // disk privat (M3-02)
            $table->timestamp('captured_at_server')->nullable(); // SERVER-side, bukan klien
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_lng', 10, 7)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'item_id']); // satu submission per item per run
            $table->foreign('crew_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_submissions');
    }
};
