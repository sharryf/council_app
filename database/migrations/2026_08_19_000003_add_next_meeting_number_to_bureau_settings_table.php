<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The next meeting number is a plain editable counter, not derived from
 * existing bureau_meetings rows — that's what lets the Module Admin set
 * it to, say, 11 when 10 meetings already happened before this system
 * existed, and reset it to 1 when a new term starts (see
 * BureauMeetingSettings and CreateMeeting::afterCreate()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->unsignedInteger('next_meeting_number')->default(1)->after('current_term_number');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->dropColumn('next_meeting_number');
        });
    }
};
