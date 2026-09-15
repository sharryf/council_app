<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row settings table (see App\Models\DocumentSigningOrganization)
 * for this module's Organization settings — currently just the
 * official stamp image used alongside signatures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signing_organizations', function (Blueprint $table) {
            $table->id();
            $table->string('stamp_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signing_organizations');
    }
};
