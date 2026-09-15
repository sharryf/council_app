<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the separate "proposed agenda" PDF generated at
 * send-for-approval time — the council only wants one agenda document,
 * the final one generated once the President actually approves the
 * meeting (see agenda_pdf_path, MeetingAgendaPdfService, and
 * MeetingResource::approveAction()). meeting_request_pdf_path is
 * unaffected — the Meeting Request letter is still generated at
 * send-for-approval time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->dropColumn('meeting_agenda_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_meetings', function (Blueprint $table) {
            $table->string('meeting_agenda_pdf_path')->nullable()->after('meeting_request_pdf_path');
        });
    }
};
