<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->text('reason');
            // {field => new value} for whichever Asset columns changed —
            // applied verbatim via Asset::update() on approval. room_id
            // and photo are deliberately never part of this: room moves
            // still go through the existing Transfer-request flow, and
            // a posted asset's photo isn't editable at all (see
            // AssetForm's photo field).
            $table->json('proposed_changes');
            $table->string('status', 20)->default('pending'); // App\Enums\AssetEditRequestStatus
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_edit_requests');
    }
};
