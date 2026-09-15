<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            // GL Code applies to top-level categories (the government
            // chart-of-accounts code, e.g. "421001" for Land) — not
            // unique on its own since a couple of top-level categories
            // in the source classification share a GL code.
            $table->string('gl_code', 20)->nullable()->after('parent_id');
            // Asset Class Code applies to sub-categories (the "Z001"
            // style code) — this is the code actually printed on asset
            // labels/registers, so it must be unique when present.
            $table->string('asset_class_code', 10)->nullable()->unique()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn(['gl_code', 'asset_class_code']);
        });
    }
};
