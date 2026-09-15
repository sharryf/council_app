<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton (always id=1) module-wide settings — currently just the
 * council's active term number, which the Module Admin updates once
 * every 5 years rather than someone picking it per meeting (see
 * App\Models\BureauSettings::current() and MeetingForm).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('current_term_number')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_settings');
    }
};
