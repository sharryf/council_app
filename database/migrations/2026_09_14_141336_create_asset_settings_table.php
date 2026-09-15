<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A minimal key/value settings table for the Assets module — same
     * shape as inventory_settings, just without the data_type/category/
     * description columns that InventorySettingsPage needs to render a
     * whole grouped form (Assets only has one setting so far: the
     * council code used in MainInventoryNo/ItemInventoryNo generation).
     */
    public function up(): void
    {
        Schema::create('asset_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->string('value', 200)->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_settings');
    }
};
