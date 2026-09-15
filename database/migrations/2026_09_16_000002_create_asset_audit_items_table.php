<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('asset_audit_sessions')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('expected_room_id')->constrained('asset_rooms'); // snapshot at session start
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->string('verify_method', 20)->nullable(); // App\Enums\AssetAuditVerifyMethod
            $table->foreignId('found_room_id')->nullable()->constrained('asset_rooms');
            $table->string('outcome', 20)->nullable(); // App\Enums\AssetAuditOutcome — computed at close
            $table->string('review_action', 20)->nullable(); // App\Enums\AssetAuditReviewAction
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'asset_id']);
            $table->index(['session_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_audit_items');
    }
};
