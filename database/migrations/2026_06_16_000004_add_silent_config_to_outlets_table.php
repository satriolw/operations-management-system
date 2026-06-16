<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-803 · konfigurasi deteksi outlet-diam tingkat-outlet: ambang (%) + basis perbandingan.
 * (Ambang tunggal per outlet sesuai desain, bukan per titik cek.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->unsignedTinyInteger('silent_threshold_pct')->default(40)->after('active');
            $table->string('comparison_basis')->default('avg_14d')->after('silent_threshold_pct');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['silent_threshold_pct', 'comparison_basis']);
        });
    }
};
