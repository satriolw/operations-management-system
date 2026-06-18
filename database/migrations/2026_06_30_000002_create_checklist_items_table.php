<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-01 · item checklist (per template). requires_photo = wajib bukti foto (anti-palsu M3-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('requires_photo')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
