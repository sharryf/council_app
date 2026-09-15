<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which speaker/attendee raised this decision request — decision
 * requests are now added from a specific person's discussion field
 * (see RecordMinutes::addDecisionForComment()/addDecisionForComposer())
 * rather than one generic per-agenda-item field, so it's worth
 * recording who proposed it, not just who (the Bureau Admin) typed it
 * in (created_by).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_decision_requests', function (Blueprint $table) {
            $table->foreignId('proposed_by')->nullable()->after('agenda_item_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bureau_decision_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_by');
        });
    }
};
