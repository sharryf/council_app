<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock takes, damage, loss, and opening balances (spec 8.4/8.6). The
 * approval gate is by adjustment *type*, not value — there is no cost
 * data in this build to gate on. Workflow logic is Phase 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 30)->unique();
            $table->date('adjustment_date');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->string('adjustment_type', 20); // STOCK_TAKE, DAMAGE, LOSS, EXPIRY, OPENING_BALANCE, CORRECTION
            $table->text('reason');
            $table->string('status', 20)->default('DRAFT'); // DRAFT, PENDING_APPROVAL, POSTED, REVERSED
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adjustment_id')->constrained('inventory_stock_adjustments')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('system_qty', 14, 3); // on_hand at the time of counting
            $table->decimal('counted_qty', 14, 3); // physical count
            $table->decimal('difference_qty', 14, 3); // counted - system (may be negative)
            $table->string('line_remarks')->nullable();

            $table->unique(['adjustment_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_adjustment_lines');
        Schema::dropIfExists('inventory_stock_adjustments');
    }
};
