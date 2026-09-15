<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('from_room_id')->constrained('asset_rooms'); // snapshot at request time
            $table->foreignId('to_room_id')->constrained('asset_rooms');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // App\Enums\AssetTransferStatus
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            // "Only one pending request per asset" (implementation plan
            // section 3.5) is enforced at the application layer (see
            // AssetResource\Transfers' requestTransferAction) rather
            // than a partial unique index — MySQL has no filtered
            // unique index, same reasoning as asset_categories' name
            // uniqueness.
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_transfer_requests');
    }
};
