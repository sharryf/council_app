<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_attachments', function (Blueprint $table) {
            // User-typed display name for a document (e.g. "Warranty
            // Card"), distinct from file_name (the uploaded file's own
            // name) — optional, only meaningful for kind='document'.
            $table->string('document_name', 160)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('asset_attachments', function (Blueprint $table) {
            $table->dropColumn('document_name');
        });
    }
};
