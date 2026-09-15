<?php

namespace Database\Seeders;

use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventorySetting;
use App\Models\InventoryUnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Phase 1 foundation seed data — units, categories, the default
 * location, and every settings key at its default (spec sections 15 &
 * 19). Roles aren't seeded as rows (see App\Enums\InventoryRole) —
 * they're enum cases assigned per-user via inventory_user_roles, the
 * same shape as Bureau's roles.
 *
 * Every call uses updateOrCreate()/firstOrCreate() so re-running this
 * seeder is safe and idempotent.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUnitsOfMeasure();
        $this->seedCategories();
        $this->seedDefaultLocation();
        $this->seedSettings();
    }

    private function seedUnitsOfMeasure(): void
    {
        $units = [
            ['code' => 'PC', 'name' => 'Piece', 'decimal_places' => 0],
            ['code' => 'BOX', 'name' => 'Box', 'decimal_places' => 0],
            ['code' => 'PKT', 'name' => 'Packet', 'decimal_places' => 0],
            ['code' => 'RM', 'name' => 'Ream', 'decimal_places' => 0],
            ['code' => 'DOZ', 'name' => 'Dozen', 'decimal_places' => 0],
            ['code' => 'M', 'name' => 'Metre', 'decimal_places' => 2],
            ['code' => 'ROLL', 'name' => 'Roll', 'decimal_places' => 0],
            ['code' => 'SET', 'name' => 'Set', 'decimal_places' => 0],
            ['code' => 'L', 'name' => 'Litre', 'decimal_places' => 2],
            ['code' => 'KG', 'name' => 'Kilogram', 'decimal_places' => 2],
            ['code' => 'BTL', 'name' => 'Bottle', 'decimal_places' => 0],
            ['code' => 'PAIR', 'name' => 'Pair', 'decimal_places' => 0],
        ];

        foreach ($units as $unit) {
            InventoryUnitOfMeasure::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['code' => 'STAT', 'name' => 'Stationery'],
            ['code' => 'ELEC', 'name' => 'Electrical'],
            ['code' => 'CLEAN', 'name' => 'Cleaning Supplies'],
            ['code' => 'IT', 'name' => 'IT Consumables'],
            ['code' => 'PANTRY', 'name' => 'Pantry Supplies'],
            ['code' => 'SAFETY', 'name' => 'Safety & First Aid'],
            ['code' => 'OTHER', 'name' => 'Other'],
        ];

        foreach ($categories as $category) {
            InventoryItemCategory::query()->updateOrCreate(['code' => $category['code']], $category);
        }
    }

    private function seedDefaultLocation(): void
    {
        InventoryLocation::query()->updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Store Room', 'is_default' => true],
        );
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => '', 'data_type' => 'string', 'category' => 'general', 'description' => 'Printed on documents'],
            ['key' => 'pdf_header_image', 'value' => '', 'data_type' => 'image', 'category' => 'general', 'description' => 'PNG shown at the top of every PDF document'],
            ['key' => 'pdf_footer_image', 'value' => '', 'data_type' => 'image', 'category' => 'general', 'description' => 'PNG shown at the bottom of every PDF document'],
            ['key' => 'date_format', 'value' => 'DD/MM/YYYY', 'data_type' => 'string', 'category' => 'general', 'description' => ''],
            ['key' => 'timezone', 'value' => 'Indian/Maldives', 'data_type' => 'string', 'category' => 'general', 'description' => 'Timezone displayed dates and times are shown in — storage stays UTC'],

            ['key' => 'allow_negative_stock', 'value' => 'false', 'data_type' => 'boolean', 'category' => 'stock', 'description' => ''],
            ['key' => 'reorder_basis', 'value' => 'available', 'data_type' => 'string', 'category' => 'stock', 'description' => 'available or on_hand'],
            ['key' => 'reorder_alert_enabled', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'stock', 'description' => ''],
            ['key' => 'reorder_digest_time', 'value' => '08:00', 'data_type' => 'string', 'category' => 'stock', 'description' => 'Daily digest send time'],
            ['key' => 'low_stock_critical_ratio', 'value' => '0.5', 'data_type' => 'decimal', 'category' => 'stock', 'description' => 'Critical bucket threshold'],
            ['key' => 'slow_moving_days', 'value' => '90', 'data_type' => 'integer', 'category' => 'stock', 'description' => ''],
            ['key' => 'consumption_window_days', 'value' => '90', 'data_type' => 'integer', 'category' => 'stock', 'description' => 'Window for avg daily usage'],
            ['key' => 'item_code_prefix_by_category', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'stock', 'description' => ''],

            ['key' => 'allow_self_approval', 'value' => 'false', 'data_type' => 'boolean', 'category' => 'approval', 'description' => ''],
            ['key' => 'require_approval_for_issue', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'approval', 'description' => 'If false, stock admins issue directly'],
            ['key' => 'default_approver_id', 'value' => '', 'data_type' => 'integer', 'category' => 'approval', 'description' => 'Fallback approver'],
            ['key' => 'approval_reminder_days', 'value' => '2', 'data_type' => 'integer', 'category' => 'approval', 'description' => 'Age at which reminders start'],
            ['key' => 'reservation_expiry_days', 'value' => '7', 'data_type' => 'integer', 'category' => 'approval', 'description' => 'Auto-release of uncollected approvals'],
            ['key' => 'require_approval_for_adjustments', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'approval', 'description' => 'Applies to DAMAGE, LOSS, CORRECTION'],
            ['key' => 'stock_take_requires_approval', 'value' => 'false', 'data_type' => 'boolean', 'category' => 'approval', 'description' => ''],
            ['key' => 'require_approval_for_grn_reversal', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'approval', 'description' => 'An admin must approve reversing a posted goods receipt'],

            ['key' => 'require_receipt_reference', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'receipt', 'description' => 'Invoice number required on GRN'],
            ['key' => 'allow_duplicate_invoice', 'value' => 'false', 'data_type' => 'boolean', 'category' => 'receipt', 'description' => 'When off, entering an invoice number already recorded for the same supplier shows a warning (does not block saving)'],
            ['key' => 'posting_lock_date', 'value' => '', 'data_type' => 'string', 'category' => 'receipt', 'description' => 'No document may post before this date'],

            ['key' => 'max_attachment_mb', 'value' => '5', 'data_type' => 'integer', 'category' => 'attachment', 'description' => 'Applies to Goods Receipt and Adjustment attachments'],
            ['key' => 'allowed_attachment_types', 'value' => '["pdf","jpg","jpeg","png","xlsx","docx"]', 'data_type' => 'json', 'category' => 'attachment', 'description' => ''],

            ['key' => 'email_notifications_enabled', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'notification', 'description' => ''],

            ['key' => 'require_purpose_on_request', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'request', 'description' => ''],
            ['key' => 'enable_signature_capture', 'value' => 'false', 'data_type' => 'boolean', 'category' => 'request', 'description' => ''],
        ];

        foreach ($settings as $setting) {
            InventorySetting::query()->updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
