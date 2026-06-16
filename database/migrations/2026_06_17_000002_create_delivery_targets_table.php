<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-804 · target pengiriman per outlet→investor (System Design §3.3/§3.8). deliver_mode
 * per target (hybrid|assisted|full_auto); pindah ke assisted/full_auto lewat gerbang kesiapan
 * (OBA aktif + group_ready). template_label = referensi template (OPS-901 menyusul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_outlet');
            $table->string('investor_label');                        // label investor (master ringan OPS-1005)
            $table->string('channel_type')->default('whatsapp');
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
            $table->string('group_id')->nullable();
            $table->boolean('group_ready')->default(false);
            $table->string('deliver_mode')->default('hybrid');        // hybrid|assisted|full_auto
            $table->string('template_label')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['id_outlet', 'active']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_targets');
    }
};
