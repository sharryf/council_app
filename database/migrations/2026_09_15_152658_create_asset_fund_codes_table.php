<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A managed list (Settings → Fund Codes), same shape as
     * asset_buildings — assets.fund_code stays a plain string column
     * (not a foreign key) so existing assets are never disturbed if a
     * code is later renamed or removed from this list, matching how
     * category/room names already work.
     */
    public function up(): void
    {
        Schema::create('asset_fund_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_fund_codes');
    }
};
