<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1004 · versi template (draft → preview → publish, rollback). Snapshot layout_json per versi;
 * publish menjadikan satu versi aktif, sisanya archived. Edit master tak live-merusak outlet pewaris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_template_id')->constrained('report_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('layout_json');
            $table->string('status')->default('draft'); // draft | published | archived
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['report_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_template_versions');
    }
};
