<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The item master, and its per-location cached stock balance
 * (inventory_item_stock — see decision D2 in the spec: on_hand/reserved
 * are maintained inside the same transaction as each ledger write, not
 * summed from inventory_stock_movements on every read). A row is
 * created here automatically whenever an item is created (Phase 2).
 *
 * Quantity-only: no cost/price/value columns anywhere on this table or
 * any other inventory table — see the spec's own repeated warning about
 * this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('inventory_item_categories');
            $table->foreignId('uom_id')->constrained('inventory_units_of_measure');
            $table->string('brand', 100)->nullable();
            $table->string('model_spec', 150)->nullable();
            $table->string('bin_location', 50)->nullable();
            $table->string('image_path')->nullable();

            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->decimal('reorder_qty', 14, 3)->default(0);
            $table->decimal('max_level', 14, 3)->nullable();
            $table->integer('lead_time_days')->nullable();

            $table->foreignId('default_supplier_id')->nullable()->constrained('inventory_suppliers')->nullOnDelete();
            $table->date('last_received_date')->nullable();
            $table->boolean('is_stock_tracked')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('category_id');
            $table->index('is_active');
        });

        // A plain auto-increment id + a unique composite index, rather
        // than a true composite primary key (Eloquent doesn't support
        // those cleanly for save()/find()) — same shape as
        // bureau_user_roles/bureau_meeting_attendees elsewhere in this
        // app. Always looked up by (item_id, location_id), never by id.
        Schema::create('inventory_item_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->decimal('on_hand', 14, 3)->default(0);
            $table->decimal('reserved', 14, 3)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamp('last_counted_at')->nullable();

            $table->unique(['item_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_stock');
        Schema::dropIfExists('inventory_items');
    }
};
