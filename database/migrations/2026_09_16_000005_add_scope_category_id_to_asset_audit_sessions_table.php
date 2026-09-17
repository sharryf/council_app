<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_audit_sessions', function (Blueprint $table) {
            // Orthogonal to scope_type/scope_building_id/scope_room_id
            // (which say *where*) — this optionally narrows the same
            // scope down to one asset category (e.g. "Computers"),
            // combinable with any of them. Null means every category.
            $table->foreignId('scope_category_id')->nullable()->after('scope_room_id')->constrained('asset_categories');
        });
    }

    public function down(): void
    {
        Schema::table('asset_audit_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scope_category_id');
        });
    }
};
