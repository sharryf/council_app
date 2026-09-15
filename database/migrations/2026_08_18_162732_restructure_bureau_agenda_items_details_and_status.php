<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Collapses the separate `title` (short, required) and `description`
 * (long, optional) fields into a single `details` long-text field — the
 * agenda table now has one "item details" column instead of two. Also
 * splits the old `approved` status into `approved` (reviewed, not yet
 * in a meeting) and `added_to_meeting` (auto-attached by CreateMeeting)
 * so the roster's actual state is visible instead of only inferable
 * from whether meeting_id is set — see App\Enums\BureauAgendaStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->text('details')->nullable()->after('meeting_id');
        });

        foreach (DB::table('bureau_agenda_items')->get() as $row) {
            $details = filled($row->description)
                ? $row->title."\n\n".$row->description
                : $row->title;

            DB::table('bureau_agenda_items')->where('id', $row->id)->update(['details' => $details]);
        }

        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });

        DB::table('bureau_agenda_items')->where('status', 'pending')->update(['status' => 'entered']);
        DB::table('bureau_agenda_items')
            ->where('status', 'approved')
            ->whereNotNull('meeting_id')
            ->update(['status' => 'added_to_meeting']);

        // The original migration's column default ('pending') is no
        // longer a valid BureauAgendaStatus value.
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->string('status')->default('entered')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
            $table->string('title')->nullable()->after('meeting_id');
            $table->text('description')->nullable()->after('title');
        });

        // Lossy: the title/description split can't be reconstructed from
        // the merged text, so this best-effort reversal just truncates
        // `details` back into a title and drops the rest.
        foreach (DB::table('bureau_agenda_items')->get() as $row) {
            DB::table('bureau_agenda_items')->where('id', $row->id)->update([
                'title' => Str::limit((string) $row->details, 255, ''),
                'description' => null,
            ]);
        }

        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->dropColumn('details');
        });

        DB::table('bureau_agenda_items')->where('status', 'entered')->update(['status' => 'pending']);
        DB::table('bureau_agenda_items')->where('status', 'added_to_meeting')->update(['status' => 'approved']);
    }
};
