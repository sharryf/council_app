<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defaults to 'posted' at the column level so every asset that
     * already exists is treated as already finalized (nothing already
     * in the register should suddenly demand a Post click) — new
     * creates explicitly pass 'draft' instead (see CreateAsset).
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('lifecycle_status', 20)->default('posted')->after('status'); // App\Enums\AssetLifecycleStatus
        });

        DB::table('assets')->update(['lifecycle_status' => 'posted']);

        Schema::table('assets', function (Blueprint $table) {
            $table->index('lifecycle_status');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('lifecycle_status');
        });
    }
};
