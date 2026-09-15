<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original schema hard-blocked a duplicate (supplier_id,
 * invoice_no) pair at the database level, bypassing the
 * 'allow_duplicate_invoice' setting entirely (it was never actually
 * read anywhere). Now that GoodsReceiptForm checks for a duplicate
 * itself and shows a warning instead — see
 * GoodsReceiptForm::duplicateInvoiceWarning() — a supplier legitimately
 * resending the same invoice number no longer needs to fail with a raw
 * SQL error; the storekeeper is warned but can still save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_goods_receipts', function (Blueprint $table) {
            // The composite unique index being dropped is also the only
            // index covering supplier_id's own foreign key — MySQL
            // refuses to drop it until another index can satisfy that
            // constraint, hence adding this one first.
            $table->index('supplier_id');
        });

        Schema::table('inventory_goods_receipts', function (Blueprint $table) {
            $table->dropUnique(['supplier_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_goods_receipts', function (Blueprint $table) {
            $table->unique(['supplier_id', 'invoice_no']);
            $table->dropIndex(['supplier_id']);
        });
    }
};
