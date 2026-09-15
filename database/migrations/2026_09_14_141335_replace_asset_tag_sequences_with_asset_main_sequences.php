<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MainInventoryNo's running sequence counts per purchase year
     * (regardless of asset class — see the sample register), not
     * globally like the old flat asset_tag counter — one locked row
     * per 2-digit year instead of a single row.
     */
    public function up(): void
    {
        Schema::dropIfExists('asset_tag_sequences');

        Schema::create('asset_main_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_main_sequences');

        Schema::create('asset_tag_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('last_number')->default(0);
        });
    }
};
