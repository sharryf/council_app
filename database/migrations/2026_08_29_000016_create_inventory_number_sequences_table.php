<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs InventorySequenceService — one row per (doc_type, year), locked
 * with lockForUpdate() and incremented atomically inside a transaction
 * on every call to next(). See that service for why this needs a row
 * lock rather than the read-then-increment pattern BureauSettings uses
 * for meeting numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A plain auto-increment id + a unique composite index rather
        // than a true composite primary key — see inventory_item_stock's
        // own comment for why (Eloquent's increment()/save() need a
        // single-column key to build their WHERE clause).
        Schema::create('inventory_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 20); // GRN, IR, ADJ, RET, MOV, ITEM
            $table->unsignedSmallInteger('year');
            $table->string('prefix', 10);
            $table->unsignedInteger('last_number')->default(0);
            $table->unsignedSmallInteger('padding')->default(4);

            $table->unique(['doc_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_number_sequences');
    }
};
