<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single locked counter row for asset_tag generation (see
     * App\Services\Assets\AssetTagGenerator) — deliberately its own
     * tiny table rather than reusing Inventory's
     * inventory_number_sequences, which belongs conceptually to the
     * Inventory module, not a general-purpose cross-module counter.
     */
    public function up(): void
    {
        Schema::create('asset_tag_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('last_number')->default(0);
        });

        DB::table('asset_tag_sequences')->insert(['last_number' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_tag_sequences');
    }
};
