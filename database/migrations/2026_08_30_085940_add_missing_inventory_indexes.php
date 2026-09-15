<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 hardening: foreignId() does not auto-index in Laravel, and
 * these two columns are plausible future filter/report columns with
 * no index today — everything else checked (stock_movements' item/date
 * and source composites, items' category/active, all three
 * issue_requests status composites) already existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('default_supplier_id');
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->index('performed_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['default_supplier_id']);
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropIndex(['performed_by']);
        });
    }
};
