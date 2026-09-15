<?php

use App\Models\InventoryIssueRequest;
use App\Models\InventoryRecipient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_issue_requests', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->after('location_id')->constrained('inventory_recipients');
        });

        // Backfill pre-existing requests (created before this field
        // existed) with a placeholder recipient, so the column can then
        // be made required without losing any rows.
        if (InventoryIssueRequest::query()->whereNull('recipient_id')->exists()) {
            $placeholder = InventoryRecipient::query()->firstOrCreate(
                ['name' => 'Unknown (legacy request)'],
            );

            InventoryIssueRequest::query()->whereNull('recipient_id')->update(['recipient_id' => $placeholder->id]);
        }

        Schema::table('inventory_issue_requests', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_issue_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recipient_id');
        });
    }
};
