<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1204 · sinyal tingkat-MERCHANT (SALDO_NEVIRA) tak punya id_outlet tunggal (saldo deposit
 * dipakai seluruh jaringan). id_outlet → nullable. Sinyal merchant-level (id_outlet null) hanya
 * tampil ke user akses-semua (owner/Finance/admin) via ScopedByOutlet; staf ter-scope tak lihat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signal_events', function (Blueprint $table) {
            $table->unsignedBigInteger('id_outlet')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('signal_events', function (Blueprint $table) {
            $table->unsignedBigInteger('id_outlet')->nullable(false)->change();
        });
    }
};
