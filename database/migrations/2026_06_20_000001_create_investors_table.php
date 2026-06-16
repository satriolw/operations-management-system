<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1005 · master investor ringan (1:1 outlet). Investor TIDAK login app — terima laporan
 * via WhatsApp; wa_contact dipakai re-invite (OPS-307) & link CRM. Bukan PII customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('wa_contact')->nullable();        // kontak WhatsApp investor (referensi re-invite)
            $table->unsignedBigInteger('id_outlet')->unique(); // 1:1 outlet
            $table->date('since_date')->nullable();          // sejak kapan jadi investor
            $table->string('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
