<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meeting names follow a fixed council-wide format built from these two
 * numbers plus the meeting type (see BureauMeeting::buildName()) — the
 * user only picks the numbers, not the whole sentence. Nullable because
 * the column is new; MeetingForm requires both going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->unsignedInteger('term_number')->nullable()->after('name');
            $table->unsignedInteger('meeting_number')->nullable()->after('term_number');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->dropColumn(['term_number', 'meeting_number']);
        });
    }
};
