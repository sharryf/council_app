<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Government asset-register fields (see
            // App\Services\Assets\AssetTagGenerator and the "Asset
            // Detailed Report.xlsx" sample this format was taken from).
            // main_inventory_no/main_sequence are nullable rather than
            // required at the DB level — every asset created through
            // the app always gets them via the generator, but keeping
            // them optional here avoids an exotic DB constraint for
            // rows a test or a future import creates directly.
            $table->string('main_inventory_no', 60)->nullable()->after('asset_tag');
            $table->unsignedInteger('main_sequence')->nullable()->after('main_inventory_no');
            // Not user-editable — derived from po_number (the segment
            // between the first two "/"s, e.g. "J-GOM" out of
            // "PO-1359/J-GOM/2023/0012") at save time.
            $table->string('fund_code', 30)->nullable()->after('vendor');
            $table->string('activity_detail', 160)->nullable()->after('fund_code');
            $table->string('po_number', 80)->nullable()->after('activity_detail');
            $table->string('voucher_number', 80)->nullable()->after('po_number');
            $table->string('sap_asset_no', 60)->nullable()->after('voucher_number');
            $table->boolean('po_void')->default(false)->after('sap_asset_no');
            $table->string('asset_type', 20)->default('purchased')->after('po_void'); // App\Enums\AssetAcquisitionType
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'main_inventory_no', 'main_sequence', 'fund_code', 'activity_detail',
                'po_number', 'voucher_number', 'sap_asset_no', 'po_void', 'asset_type',
            ]);
        });
    }
};
