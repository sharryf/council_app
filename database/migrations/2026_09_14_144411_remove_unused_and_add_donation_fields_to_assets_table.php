<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['activity_detail', 'po_void', 'sap_asset_no']);
            // Only meaningful when asset_type is "donated" — the
            // donor's own reference for the donation document.
            $table->string('donation_reference_no', 80)->nullable()->after('voucher_number');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('donation_reference_no');
            $table->string('activity_detail', 160)->nullable();
            $table->boolean('po_void')->default(false);
            $table->string('sap_asset_no', 60)->nullable();
        });
    }
};
