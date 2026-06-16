<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-804 · akun WhatsApp pengirim (System Design §3.3). Kredensial TIDAK disimpan mentah —
 * hanya credentials_ref ke secret store (aturan emas #7). oba_status & account_status menentukan
 * kelayakan mode assisted/full_auto (gerbang kesiapan OPS-306).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedBigInteger('id_outlet')->nullable();
            $table->string('phone_number');                              // ditampilkan ter-mask
            $table->string('provider')->default('meta_cloud');
            $table->string('oba_status')->default('none');               // active|process|none
            $table->string('account_status')->default('active');         // active|lost|recovering
            $table->string('credentials_ref')->nullable();               // referensi secret store, BUKAN secret
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
