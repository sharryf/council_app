<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create Asset no longer requires a category up front (simplified
     * per request — an asset can be tagged/located immediately and
     * categorized later via Edit). AssetTagGenerator already falls back
     * to a "GEN" class code when the category (or its class code) is
     * missing, so numbering still works with no category selected.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->change();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('asset_categories');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('asset_categories');
        });
    }
};
