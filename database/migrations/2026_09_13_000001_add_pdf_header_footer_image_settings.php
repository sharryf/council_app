<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repurposes the never-wired-up 'company_logo_url' setting (it read
 * from nowhere — see InventorySettingsPage::UNUSED_KEYS) into an
 * uploaded PNG shown in every inventory PDF's header, and adds its
 * footer counterpart. Renamed rather than left as-is since the key's
 * old name promised a URL, not an uploaded file's storage path.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_settings')->where('key', 'company_logo_url')->update([
            'key' => 'pdf_header_image',
            'data_type' => 'image',
            'description' => 'PNG shown at the top of every PDF document',
        ]);

        DB::table('inventory_settings')->updateOrInsert(
            ['key' => 'pdf_footer_image'],
            ['value' => '', 'data_type' => 'image', 'category' => 'general', 'description' => 'PNG shown at the bottom of every PDF document'],
        );
    }

    public function down(): void
    {
        DB::table('inventory_settings')->where('key', 'pdf_footer_image')->delete();

        DB::table('inventory_settings')->where('key', 'pdf_header_image')->update([
            'key' => 'company_logo_url',
            'data_type' => 'string',
            'description' => 'Printed on documents',
        ]);
    }
};
