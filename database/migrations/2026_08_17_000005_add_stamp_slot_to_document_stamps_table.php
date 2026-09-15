<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which of the organization's two stamp slots (see
 * App\Models\DocumentSigningOrganization) this placement uses. Defaults
 * to 1 so existing rows, all placed back when there was only ever one
 * stamp, keep pointing at the same image.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_stamps', function (Blueprint $table) {
            $table->unsignedTinyInteger('stamp_slot')->default(1)->after('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_stamps', function (Blueprint $table) {
            $table->dropColumn('stamp_slot');
        });
    }
};
