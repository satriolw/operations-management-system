<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1101 · master data kapasitas outlet (Epic K, System Design §3.14). Effective capacity
 * diturunkan dari input (mesin × throughput, atau kg/hari ÷ jam shift) dengan opsi override
 * (capacity_kg_per_hour). Ambang overload per-outlet. Dikonsumsi OPS-1103 (utilisasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_capacity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->decimal('kg_per_day', 8, 2)->nullable();                 // throughput harian (manual/informasional)
            $table->unsignedInteger('machines')->nullable();                 // jumlah mesin
            $table->decimal('shift_hours', 4, 1)->nullable();                // jam operasional/hari (window)
            $table->decimal('throughput_kg_per_machine_hour', 6, 2)->nullable(); // kg/jam/mesin
            $table->decimal('capacity_kg_per_hour', 8, 2)->nullable();       // OVERRIDE effective capacity
            $table->unsignedTinyInteger('overload_threshold_pct')->default(80);
            $table->timestamps();

            $table->unique('id_outlet'); // 1:1 outlet
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_capacity');
    }
};
