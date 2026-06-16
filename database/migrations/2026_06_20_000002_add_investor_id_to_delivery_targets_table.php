<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPS-1005 · tautkan delivery_targets ke master investor (sebelumnya hanya investor_label string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_targets', function (Blueprint $table) {
            $table->foreignId('investor_id')->nullable()->after('id_outlet')
                ->constrained('investors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investor_id');
        });
    }
};
