<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two documents generated together when a meeting is sent for the
 * President's approval (see MeetingResource::sendForApprovalAction()
 * and MeetingApprovalPacketPdfService) — distinct from the pre-existing
 * agenda_pdf_path, which is a different document generated later, only
 * once the meeting is actually approved/Scheduled, and emailed to
 * attendees (see MeetingResource::approveAction() and
 * MeetingScheduledMail). Three similarly-named *_pdf_path columns on
 * this table, on purpose — don't conflate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->string('meeting_request_pdf_path')->nullable()->after('agenda_pdf_path');
            $table->string('meeting_agenda_pdf_path')->nullable()->after('meeting_request_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->dropColumn(['meeting_request_pdf_path', 'meeting_agenda_pdf_path']);
        });
    }
};
