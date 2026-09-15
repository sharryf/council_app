<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a movement back to the specific issue batch (InventoryIssueReceipt)
 * that created it, so a receipt's "what was issued in this batch" can be
 * read straight off the existing immutable ledger rather than duplicating
 * quantities into a second line-breakdown table. Nullable — only Issue-type
 * movements created via IssueGoods ever set this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->foreignId('issue_receipt_id')->nullable()->after('source_no')->constrained('inventory_issue_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_receipt_id');
        });
    }
};
