<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1201 · snapshot saldo deposit tingkat-merchant NEVIRA (Epic L, System Design §3.15).
 * Hanya saldo_total + breakdown (count per aksi) — BUKAN history. Burn/runway (OPS-1202)
 * diturunkan dari delta antar-snapshot. Output ber-stempel waktu (WIB), query-able LBE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nevira_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('captured_at');
            $table->bigInteger('saldo_total'); // rupiah; merchant-level (single point of failure)
            $table->json('breakdown_json')->nullable(); // count per aksi (nota/struk/wa/export)
            $table->timestamps();

            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nevira_balance_snapshots');
    }
};
