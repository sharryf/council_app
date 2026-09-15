<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrades the bare "was this user marked attending" pivot into a real
 * three-way roll call: which roster the row belongs to (Council vs
 * Secretariat — see App\Enums\BureauMinutesAttendanceGroup) and a
 * Present/Absent/On Leave status (see App\Enums\BureauAttendanceStatus,
 * Secretariat rows only ever use Present/Absent). attended_at is
 * Council-only in the UI — Secretariat is a simple checkbox with no
 * time. See BureauMeetingMinutes::councilAttendance()/
 * secretariatAttendance() and RecordMinutes's attendance card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_minutes_attendance', function (Blueprint $table) {
            $table->string('role_group')->default('council')->after('user_id');
            $table->string('status')->default('absent')->after('role_group');
            $table->dateTime('attended_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_minutes_attendance', function (Blueprint $table) {
            $table->dropColumn(['role_group', 'status', 'attended_at']);
        });
    }
};
