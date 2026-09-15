<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The module now supports two named organization stamps (e.g. two
 * different offices' stamps) rather than one — the uploader picks
 * which one to place per-document in the wizard. `stamp_path` stays as
 * slot 1's column; `stamp_path_2` is the second slot. Labels are
 * optional — App\Models\DocumentSigningOrganization falls back to
 * "Stamp 1"/"Stamp 2" when blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signing_organizations', function (Blueprint $table) {
            $table->string('stamp_label')->nullable()->after('stamp_path');
            $table->string('stamp_path_2')->nullable()->after('stamp_label');
            $table->string('stamp_label_2')->nullable()->after('stamp_path_2');
        });
    }

    public function down(): void
    {
        Schema::table('document_signing_organizations', function (Blueprint $table) {
            $table->dropColumn(['stamp_label', 'stamp_path_2', 'stamp_label_2']);
        });
    }
};
