<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1302 · master data SLA produksi per outlet (Epic M, System Design §3.16). Mode jam
 * (business_hours: overdue diukur jam operasional; wallclock: apa adanya) + ambang. CRUD Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_sla_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->string('sla_clock_mode')->default('business_hours'); // business_hours|wallclock
            $table->unsignedInteger('grace_minutes')->default(30);
            $table->unsignedInteger('approaching_lead_minutes')->default(120);
            $table->unsignedInteger('stuck_minutes_threshold')->default(240);
            $table->unsignedInteger('minor_overdue_minutes')->default(120);
            $table->timestamps();

            $table->unique('id_outlet');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_sla_config');
    }
};
