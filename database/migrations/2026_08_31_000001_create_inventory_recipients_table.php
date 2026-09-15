<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People who receive issued goods but have no login of their own —
 * a stock admin or other user raises the Issue Request on their
 * behalf. Kept as a lightweight registry, the same shape as
 * inventory_suppliers, rather than reusing the users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('identification_no', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('department', 150)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_recipients');
    }
};
