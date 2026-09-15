<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_audit_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('scope_type', 20); // App\Enums\AssetAuditScopeType
            $table->foreignId('scope_building_id')->nullable()->constrained('asset_buildings');
            $table->foreignId('scope_room_id')->nullable()->constrained('asset_rooms');
            $table->string('status', 20)->default('in_progress'); // App\Enums\AssetAuditSessionStatus
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('started_at');
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // "Only one in-progress session at a time" (implementation
            // plan section 3.8) enforced at the application layer, same
            // reasoning as every other partial-unique rule in this
            // module — MySQL has no filtered unique index.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_sessions');
    }
};
