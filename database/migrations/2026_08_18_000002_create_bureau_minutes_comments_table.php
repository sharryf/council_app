<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single comment recorded against one agenda item during the live
 * meeting — bullet_points is the Bureau Admin's raw talking-point
 * input, drafted_text is the AI-expanded (or manually written/edited)
 * prose that actually appears in the minutes. Any attendee may edit
 * drafted_text after the meeting ends (see BureauMeetingMinutes'
 * Review status) — edited_by/edited_at track the most recent editor
 * without needing a full revision history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_minutes_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('bureau_meeting_minutes')->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->constrained('bureau_agenda_items')->cascadeOnDelete();
            $table->foreignId('speaker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('bullet_points')->nullable();
            $table->text('drafted_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_minutes_comments');
    }
};
