<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1402..1406 · ambang audit transaksi per outlet (Epic N, System Design §3.17). CRUD Admin;
 * tanpa hardcode. Default aman dari config/transaction_audit.php untuk outlet baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_audit_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->decimal('promo_leak_pct', 5, 2)->default(15);
            $table->bigInteger('promo_leak_daily_cap')->default(500000);
            $table->bigInteger('payment_anomaly_min_amount')->default(50000);
            $table->decimal('offprice_tolerance_pct', 5, 2)->default(5);
            $table->decimal('qty_variance_pct', 5, 2)->default(20);
            $table->unsignedInteger('deposit_expiry_lead_days')->default(14);
            $table->timestamps();

            $table->unique('id_outlet');
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_audit_config');
    }
};
