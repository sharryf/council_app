<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Someone who has left is deactivated, never deleted —
            // every approval/request/signature in the app is a
            // permanent FK to a user row, so deleting one who has any
            // history breaks (correctly) with a constraint violation.
            // Deactivating blocks login while keeping their name intact
            // everywhere it's referenced.
            $table->boolean('is_active')->default(true)->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
