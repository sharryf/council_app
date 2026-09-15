<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The movement ledger — the source of truth for every quantity change
 * (decision D1 in the spec). Rows are immutable: never updated or
 * deleted, only reversed by writing a new REVERSAL_* row. The actual
 * write path (locking inventory_item_stock, inserting here, updating
 * the cached balance, all inside one transaction) is Phase 3 — this
 * migration only lays down the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_no', 30)->unique();
            $table->dateTime('movement_date');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->foreignId('location_id')->constrained('inventory_locations');

            $table->string('movement_type', 20); // App\Enums\InventoryMovementType
            $table->smallInteger('direction'); // +1 = IN, -1 = OUT
            $table->decimal('quantity', 14, 3); // always positive; direction carries the sign
            $table->decimal('balance_after', 14, 3); // on_hand immediately after this movement

            $table->string('source_type', 20); // GRN, ISSUE, ADJUSTMENT, RETURN, OPENING, REVERSAL
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();
            $table->string('source_no', 30)->nullable(); // denormalized doc number for fast display

            $table->foreignId('reversal_of_id')->nullable()->constrained('inventory_stock_movements')->nullOnDelete();
            $table->boolean('is_reversed')->default(false);

            $table->string('reference', 100)->nullable(); // invoice no, PO no, free text
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['item_id', 'movement_date', 'id']);
            $table->index(['source_type', 'source_id']);
            $table->index('movement_date');
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
