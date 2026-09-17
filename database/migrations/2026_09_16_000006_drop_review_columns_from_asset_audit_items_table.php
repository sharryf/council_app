<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audits no longer have a review queue — a room correction now goes
 * through an ordinary AssetTransferRequest instead
 * (ViewAssetAuditSession::foundInAnotherRoom()), and an item left
 * unverified at close is just tagged AssetAuditOutcome::Missing with
 * no further action. These columns were never anything but that now-
 * removed mechanism's own bookkeeping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_audit_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_action', 'reviewed_at', 'review_note']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_audit_items', function (Blueprint $table) {
            $table->string('review_action', 20)->nullable(); // App\Enums\AssetAuditReviewAction
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
        });
    }
};
