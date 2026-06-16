<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-901 · template laporan dinamis (System Design §3.9). Master (grup) → override per
 * outlet/target via parent_template_id. layout_json = urutan blok + token + teks statis.
 * meta_template_ref = referensi approved Meta template (transport Opsi A, OPS-903).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->default('master');        // master|outlet|target
            $table->foreignId('parent_template_id')->nullable()->constrained('report_templates')->nullOnDelete();
            $table->unsignedBigInteger('id_outlet')->nullable(); // utk scope=outlet
            $table->string('name');
            $table->json('layout_json');
            $table->string('meta_template_ref')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['scope', 'id_outlet', 'active']);
            $table->foreign('id_outlet')->references('id_outlet')->on('outlets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
