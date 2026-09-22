<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The is_active column was added with ->default(true), which MySQL
     * normally backfills onto existing rows on its own — but a site
     * that ran this migration under different circumstances (e.g. the
     * column already existed nullable before the default was added, or
     * a raw insert bypassed it) can still end up with genuine NULLs,
     * which crashed canAccessPanel() with a TypeError instead of just
     * gating (see App\Models\User::canAccessPanel()). This is a one-time
     * data fix, not a schema change — treats a NULL the same as the
     * column's own intended default rather than guessing it should be
     * false.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('is_active')->update(['is_active' => true]);
    }

    /**
     * Not reversible — there's no way to tell which rows were
     * genuinely NULL before this ran versus already true.
     */
    public function down(): void
    {
        //
    }
};
