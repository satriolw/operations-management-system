<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1405 · variance quantity vs actual_quantity MEMPERKUAT KPI akurasi input (Epic N, §3.17(d)).
 * Bukan sinyal baru — tambah kolom agregat ke cashier_input_scores (existing OPS-603). Guard
 * hasColumn (idempoten bila sudah ada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cashier_input_scores', 'qty_variance_count')) {
            Schema::table('cashier_input_scores', function (Blueprint $table) {
                $table->unsignedInteger('qty_variance_count')->default(0)->after('txn_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cashier_input_scores', 'qty_variance_count')) {
            Schema::table('cashier_input_scores', function (Blueprint $table) {
                $table->dropColumn('qty_variance_count');
            });
        }
    }
};
