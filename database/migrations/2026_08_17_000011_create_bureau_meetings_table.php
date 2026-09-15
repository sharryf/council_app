<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scheduled council meeting — see App\Enums\BureauMeetingStatus.
 * Approved agenda items auto-attach here (see the migration adding
 * meeting_id to bureau_agenda_items) at creation time. Once the
 * Council President approves it (Scheduled), agenda_pdf_path holds the
 * generated agenda PDF that gets emailed to every attendee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_meetings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('draft'); // App\Enums\BureauMeetingStatus
            $table->string('agenda_pdf_path')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bureau_meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('bureau_meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_meeting_attendees');
        Schema::dropIfExists('bureau_meetings');
    }
};
