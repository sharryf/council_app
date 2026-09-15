<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Once an approved agenda item is auto-attached to a meeting (see
 * CreateMeeting), it's "claimed" and drops out of the pool of items
 * available to be pulled into a future meeting — nullOnDelete rather
 * than cascading, so deleting a meeting just releases its items back
 * into that pool instead of destroying them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->after('id')
                ->constrained('bureau_meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bureau_agenda_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('meeting_id');
        });
    }
};
