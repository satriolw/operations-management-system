<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-606 · tautan opsional user OMS → aktor NEVIRA (id_user), untuk aturan reviewer ≠ subjek.
 * Null bila belum ditautkan (tak bisa dipastikan → tinjauan tetap diizinkan, tapi tak menutup subjek-diri).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('nevira_user_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nevira_user_id');
        });
    }
};
