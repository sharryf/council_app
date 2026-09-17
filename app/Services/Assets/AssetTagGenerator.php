<?php

namespace App\Services\Assets;

use App\Models\AssetSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the register-style MainInventoryNo/ItemInventoryNo pair and
 * the unguessable public_token used in QR public URLs — format and
 * numbering rules taken directly from a real council's current asset
 * register ("Asset Detailed Report.xlsx"): {council code}-{2-digit
 * purchase year}-{asset class code}-{sequence}, where the sequence
 * counts per year across every class (not reset per class). Each asset
 * is its own "Main" (no shared batches/quantities), so ItemInventoryNo
 * is always the Main plus a constant "-1" suffix — kept as a suffix
 * rather than dropped so the value still reads like the source
 * register's ItemInventoryNo column.
 *
 * asset_tag keeps its existing role as the app-wide unique human-facing
 * identifier (QR public page, audit scanning, transfers/maintenance,
 * exports) — only its format changed, not what it's used for.
 */
class AssetTagGenerator
{
    /**
     * @return array{main_inventory_no: string, main_sequence: int, asset_tag: string}
     */
    public function generate(?Carbon $purchaseDate, string $classCode): array
    {
        $year = (int) ($purchaseDate?->format('y') ?? now()->format('y'));

        $mainSequence = DB::transaction(function () use ($year): int {
            DB::table('asset_main_sequences')->updateOrInsert(['year' => $year], []);

            DB::table('asset_main_sequences')->where('year', $year)->lockForUpdate()->first();
            DB::table('asset_main_sequences')->where('year', $year)->increment('last_number');

            return (int) DB::table('asset_main_sequences')->where('year', $year)->value('last_number');
        });

        $councilCode = AssetSetting::get('council_code', '000');
        $yearPart = str_pad((string) $year, 2, '0', STR_PAD_LEFT);
        $mainInventoryNo = "{$councilCode}-{$yearPart}-{$classCode}-{$mainSequence}";

        return [
            'main_inventory_no' => $mainInventoryNo,
            'main_sequence' => $mainSequence,
            'asset_tag' => "{$mainInventoryNo}-1",
        ];
    }

    /**
     * 128+ bits of entropy, URL-safe — enumerating this must be
     * infeasible (implementation plan section 2.3).
     */
    public function newPublicToken(): string
    {
        return Str::random(32);
    }
}
