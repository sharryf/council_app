<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Pages\InventorySettingsPage;
use App\Models\InventoryAuditLog;
use App\Models\InventorySetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class InventorySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::Admin]);

        return $user;
    }

    private function seedSetting(string $key, string $value, string $dataType = 'boolean', string $category = 'stock'): void
    {
        InventorySetting::create(['key' => $key, 'value' => $value, 'data_type' => $dataType, 'category' => $category]);
    }

    public function test_get_reads_the_seeded_value(): void
    {
        $this->seedSetting('allow_negative_stock', 'false');

        $this->assertFalse(InventorySetting::getBool('allow_negative_stock'));
    }

    /**
     * A write that bypasses Eloquent (raw DB::table()->update()) proves
     * the read is actually served from cache, not the database — a
     * write through the model afterwards proves the saved() hook
     * invalidates it correctly.
     */
    public function test_reads_are_cached_until_a_save_invalidates_them(): void
    {
        $this->seedSetting('allow_negative_stock', 'false');
        $this->assertFalse(InventorySetting::getBool('allow_negative_stock'));

        DB::table('inventory_settings')->where('key', 'allow_negative_stock')->update(['value' => 'true']);
        $this->assertFalse(InventorySetting::getBool('allow_negative_stock'), 'stale DB write should not be visible until the cache is invalidated');

        InventorySetting::find('allow_negative_stock')->update(['value' => 'true']);
        $this->assertTrue(InventorySetting::getBool('allow_negative_stock'), 'an Eloquent save should invalidate the cache immediately');
    }

    public function test_a_non_admin_cannot_reach_the_settings_page(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        $this->actingAs($user)->get(InventorySettingsPage::getUrl())->assertForbidden();
    }

    /**
     * Regression test: mount() must fill Toggle fields with a real PHP
     * boolean, not the stored 'true'/'false' string — PHP casts any
     * non-empty string (including the literal 'false') to true, which
     * left every boolean setting displaying as ON regardless of its
     * actual value (caught live, not by the other tests here, since
     * they all use fillForm() to set state directly rather than
     * exercising mount()'s own fill path).
     */
    public function test_mount_reflects_a_false_boolean_setting_as_off_not_on(): void
    {
        $this->seedSetting('allow_negative_stock', 'false', category: 'stock');

        $this->actingAs($this->makeAdmin());

        Livewire::test(InventorySettingsPage::class)
            ->assertFormSet(['allow_negative_stock' => false]);
    }

    public function test_saving_the_settings_page_persists_changes_and_writes_an_audit_row(): void
    {
        $this->seedSetting('allow_negative_stock', 'false', category: 'stock');
        $this->seedSetting('approval_reminder_days', '2', 'integer', 'approval');

        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        Livewire::test(InventorySettingsPage::class)
            ->fillForm([
                'allow_negative_stock' => true,
                'approval_reminder_days' => '5',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(InventorySetting::getBool('allow_negative_stock'));
        $this->assertSame('5', InventorySetting::get('approval_reminder_days'));

        $log = InventoryAuditLog::where('entity_type', 'SETTINGS')->sole();
        $this->assertSame('UPDATE', $log->action);
        $this->assertSame('false', $log->old_values['allow_negative_stock']);
        $this->assertSame('true', $log->new_values['allow_negative_stock']);
        $this->assertSame($admin->id, $log->changed_by);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $this->seedSetting('allow_negative_stock', 'false');

        $this->actingAs($this->makeAdmin());

        Livewire::test(InventorySettingsPage::class)
            ->fillForm(['allow_negative_stock' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, InventoryAuditLog::where('entity_type', 'SETTINGS')->count());
    }
}
