<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live-recorded minutes for a single meeting (one-to-one) — see
 * App\Enums\BureauMinutesStatus. Started/ended by the Bureau Admin
 * during the meeting itself; audio_path is an archive-only recording,
 * never fed to the AI drafting service (see MinutesDraftingService).
 * Once the Council President approves it, minutes_pdf_path holds the
 * final generated PDF and document_id links to the Document Signing
 * record it was handed off to (see MeetingResource-equivalent's
 * approveMinutesAction()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained('bureau_meetings')->cascadeOnDelete();
            $table->string('status')->default('draft'); // App\Enums\BureauMinutesStatus
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->text('introduction')->nullable();
            $table->text('closing_notes')->nullable();
            $table->string('audio_path')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('minutes_pdf_path')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bureau_minutes_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('bureau_meeting_minutes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['minutes_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_minutes_attendance');
        Schema::dropIfExists('bureau_meeting_minutes');
    }
};
