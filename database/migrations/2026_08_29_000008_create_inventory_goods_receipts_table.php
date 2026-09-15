<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock IN — a GRN can cover many items from one invoice/PO/delivery
 * note. There are no purchase-order records: po_no is a plain text
 * reference field, same as invoice_no (spec section 6.5). The
 * create/post/reverse workflow (spec 8.1) is Phase 4 — this migration
 * only lays down the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no', 30)->unique();
            $table->date('receipt_date');
            $table->foreignId('location_id')->constrained('inventory_locations');
            $table->foreignId('supplier_id')->constrained('inventory_suppliers');

            $table->string('reference_type', 10)->default('INVOICE'); // INVOICE, PO, BOTH, NONE
            $table->string('invoice_no', 50)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('po_no', 50)->nullable();
            $table->string('delivery_note_no', 50)->nullable();

            $table->string('status', 20)->default('DRAFT'); // DRAFT, POSTED, REVERSED
            $table->text('remarks')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Blocks the same invoice being received twice for one
            // supplier — MySQL allows multiple NULLs through a unique
            // index, so receipts with no invoice number are unaffected.
            $table->unique(['supplier_id', 'invoice_no']);
            $table->index('po_no');
            $table->index('invoice_no');
            $table->index('receipt_date');
        });

        Schema::create('inventory_goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('inventory_goods_receipts')->cascadeOnDelete();
            $table->unsignedInteger('line_no');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->decimal('quantity', 14, 3);
            $table->string('remarks')->nullable();

            $table->unique(['grn_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_goods_receipt_lines');
        Schema::dropIfExists('inventory_goods_receipts');
    }
};
