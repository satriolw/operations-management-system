<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-802 · assignment user↔outlet (scoping). Fondasi otorisasi per-outlet (OPS-1003):
 * staf internal hanya melihat outlet yang di-assign. Admin = akses semua (tanpa baris di sini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_outlet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('id_outlet');
            $table->timestamps();

            $table->unique(['user_id', 'id_outlet']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_outlet');
    }
};
