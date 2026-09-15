<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each user can now save two signatures (see App\Models\User) and pick
 * which one is used by default wherever a signature is needed — the
 * same two-slot pattern already used for organization stamps (see the
 * add_second_stamp_to_document_signing_organizations_table migration).
 * `signature_path` stays as slot 1; `default_signature_slot` defaults
 * to 1 so existing users, who only ever had one signature, keep using
 * it unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path_2')->nullable()->after('signature_path');
            $table->unsignedTinyInteger('default_signature_slot')->default(1)->after('signature_path_2');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['signature_path_2', 'default_signature_slot']);
        });
    }
};
