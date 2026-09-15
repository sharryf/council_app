<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public/audience attendees at a council meeting — unlike every other
 * attendee-shaped record in this module, these are never a `User`
 * (they don't log in), just a free-text name + address the Bureau
 * Admin types in during roll call. See
 * App\Models\BureauMinutesListener and RecordMinutes's attendance
 * card's third sub-section (އަޑުއެހުމަށް ހާޟިރުވި ފަރާތްތައް).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_minutes_listeners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('bureau_meeting_minutes')->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_minutes_listeners');
    }
};
