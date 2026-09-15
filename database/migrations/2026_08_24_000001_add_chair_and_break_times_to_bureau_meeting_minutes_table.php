<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The administrative record a real council minutes document needs
 * beyond started_at/ended_at — who chaired, and the break window (if
 * any). See App\Models\BureauMeetingMinutes::chair() and
 * RecordMinutes's meeting-info card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_meeting_minutes', function (Blueprint $table) {
            $table->foreignId('chaired_by')->nullable()->after('meeting_id')->constrained('users')->nullOnDelete();
            $table->dateTime('break_started_at')->nullable()->after('started_at');
            $table->dateTime('break_ended_at')->nullable()->after('break_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_meeting_minutes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chaired_by');
            $table->dropColumn(['break_started_at', 'break_ended_at']);
        });
    }
};
