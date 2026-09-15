<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Public and emergency meetings are numbered in separate sequences
 * (see App\Models\BureauSettings and MeetingForm) — replaces the single
 * next_meeting_number counter with one per type. Both start from
 * whatever the single counter already held, so in-progress numbering
 * isn't disrupted by the split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->unsignedInteger('next_public_meeting_number')->default(1)->after('current_term_number');
            $table->unsignedInteger('next_private_meeting_number')->default(1)->after('next_public_meeting_number');
        });

        DB::table('bureau_settings')->update([
            'next_public_meeting_number' => DB::raw('next_meeting_number'),
            'next_private_meeting_number' => DB::raw('next_meeting_number'),
        ]);

        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->dropColumn('next_meeting_number');
        });
    }

    public function down(): void
    {
        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->unsignedInteger('next_meeting_number')->default(1)->after('current_term_number');
        });

        DB::table('bureau_settings')->update([
            'next_meeting_number' => DB::raw('next_public_meeting_number'),
        ]);

        Schema::table('bureau_settings', function (Blueprint $table) {
            $table->dropColumn(['next_public_meeting_number', 'next_private_meeting_number']);
        });
    }
};
