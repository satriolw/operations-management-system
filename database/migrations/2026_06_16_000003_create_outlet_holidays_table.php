<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-803/OPS-106 · hari libur spesifik per outlet (tanggal). Outlet tutup → suppress alarm
 * outlet-diam & framing laporan (OPS-1001). 'note' bukan PII customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->date('holiday_date');
            $table->string('note')->nullable(); // mis. "Idul Fitri" — bukan PII
            $table->timestamps();

            $table->unique(['id_outlet', 'holiday_date']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_holidays');
    }
};
