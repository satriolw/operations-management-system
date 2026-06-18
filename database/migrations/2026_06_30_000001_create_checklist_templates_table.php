<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M3-01 · master template checklist (Modul 3 Discipline, System Design §3). id_outlet null = template
 * grup (diwarisi semua outlet); diisi → khusus outlet. schedule daily|shift. CRUD via Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet')->nullable(); // null = grup (semua outlet)
            $table->string('name');
            $table->string('schedule')->default('daily'); // daily|shift
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['id_outlet', 'active']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
    }
};
