<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an agenda item be a procedural "Agenda Passing" or "Minutes
 * Passing" motion instead of a regular proposed item — see
 * App\Enums\BureauAgendaItemKind. related_meeting_id is only set for
 * Minutes Passing items, pointing at the specific past meeting whose
 * minutes are being passed. nullOnDelete (not cascade) so deleting that
 * past meeting orphans the reference instead of destroying this item
 * and its votes — same reasoning as the existing meeting_id column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->string('kind')->default('regular')->after('details');
            $table->foreignId('related_meeting_id')->nullable()->after('kind')
                ->constrained('bureau_meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_meeting_id');
            $table->dropColumn('kind');
        });
    }
};
