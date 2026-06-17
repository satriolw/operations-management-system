<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-805 · peta referensi id_role NEVIRA → level (untuk OPS-601 self-approval).
 * dual_authority_allowed = true bila role setara/di atas Kepala Toko (wewenang request+approve sah).
 * Master data dikelola via Admin (tidak ada hardcode di logika domain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nevira_role_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_role')->unique(); // id_role aktor NEVIRA
            $table->string('label');
            $table->unsignedTinyInteger('level')->default(0); // makin tinggi makin senior
            $table->boolean('dual_authority_allowed')->default(false); // >= Kepala Toko
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nevira_role_levels');
    }
};
