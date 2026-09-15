<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A decision request raised against one agenda item during the live
 * meeting — sort_order is the order it was raised in, which is also
 * the order votes are taken in (see App\Services\Bureau\
 * DecisionVotingService). Once one request for an agenda item passes
 * by simple majority of total council membership, every other Pending
 * request for that same agenda item is marked Skipped rather than
 * voted on — see App\Enums\BureauDecisionStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bureau_decision_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_id')->constrained('bureau_meeting_minutes')->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->constrained('bureau_agenda_items')->cascadeOnDelete();
            $table->text('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('pending'); // App\Enums\BureauDecisionStatus
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('bureau_decision_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_request_id')->constrained('bureau_decision_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('vote'); // App\Enums\BureauVoteChoice
            $table->timestamps();

            $table->unique(['decision_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bureau_decision_votes');
        Schema::dropIfExists('bureau_decision_requests');
    }
};
