<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One immutable row per issue batch — who received it and signed for
 * it, and when, and which staff member processed it. Mirrors
 * inventory_stock_movements' "one ledger line per event, never
 * updated after creation" philosophy, so a request issued to two
 * different people across two visits keeps both signatures instead
 * of the second silently overwriting the first (the previous design,
 * where received_by_name/receiver_signature_path lived as mutable
 * columns directly on inventory_issue_requests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_issue_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('inventory_issue_requests')->cascadeOnDelete();
            $table->foreignId('issued_by')->constrained('users');
            $table->dateTime('issued_at');
            $table->string('received_by_name', 100);
            $table->string('receiver_signature_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_issue_receipts');
    }
};
