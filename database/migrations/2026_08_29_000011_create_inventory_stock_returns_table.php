<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unused items returned to store, optionally linked back to the
 * original issue request (spec 8.5). Only GOOD-condition lines
 * increase on_hand; DAMAGED lines are recorded for the audit trail but
 * create no movement. Workflow logic is Phase 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 30)->unique();
            $table->date('return_date');
            $table->foreignId('issue_request_id')->nullable()->constrained('inventory_issue_requests')->nullOnDelete();
            $table->foreignId('returned_by')->constrained('users');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT, POSTED, REVERSED
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_stock_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('inventory_stock_returns')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('issue_line_id')->nullable()->constrained('inventory_issue_request_lines')->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('condition', 20)->default('GOOD'); // GOOD, DAMAGED
            $table->string('line_remarks')->nullable();

            $table->unique(['return_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_return_lines');
        Schema::dropIfExists('inventory_stock_returns');
    }
};
