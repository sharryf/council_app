<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * AssetStatus dropped In Storage, Under Repair, and Retired in
     * favour of Damaged and Auctioned (request: "Under repair not
     * needed as a status") — any row still holding one of the old
     * values would fail to load at all (App\Enums\AssetStatus is a
     * backed enum; an unrecognized value throws on cast). Remaps in
     * place rather than blocking on it:
     *   in_storage   -> in_use    (still on hand, just not deployed)
     *   under_repair -> damaged   (closest match — the status
     *                              AssetResource::logMaintenanceAction()
     *                              now sets instead of under_repair)
     *   retired      -> disposed  (no longer in service; Disposed is
     *                              the remaining "no longer owned" state)
     */
    public function up(): void
    {
        $map = ['in_storage' => 'in_use', 'under_repair' => 'damaged', 'retired' => 'disposed'];

        foreach ($map as $old => $new) {
            DB::table('assets')->where('status', $old)->update(['status' => $new]);
            DB::table('asset_maintenance_records')->where('previous_status', $old)->update(['previous_status' => $new]);
            DB::table('asset_maintenance_records')->where('closing_status', $old)->update(['closing_status' => $new]);
        }
    }

    /**
     * Best-effort only — the original in_storage/under_repair/retired
     * distinction can't be recovered once collapsed into their new
     * equivalents.
     */
    public function down(): void
    {
        DB::table('assets')->where('status', 'damaged')->update(['status' => 'under_repair']);
    }
};
