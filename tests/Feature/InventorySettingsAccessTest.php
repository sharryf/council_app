<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Pages\ItemDataPage;
use App\Filament\Inventory\Resources\ItemCategories\ItemCategoryResource;
use App\Filament\Inventory\Resources\Locations\LocationResource;
use App\Filament\Inventory\Resources\Recipients\RecipientResource;
use App\Filament\Inventory\Resources\Suppliers\SupplierResource;
use App\Filament\Inventory\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A plain User must see no Settings items at all, and a Stock-Admin-
 * only viewer must see only Suppliers/Recipients (the two reference
 * lists actually used while receiving/issuing stock) — everything else
 * under Settings is Admin-only.
 */
class InventorySettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));
    }

    private function makeUser(InventoryRole $role): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => $role]);

        return $user;
    }

    public function test_a_plain_user_cannot_reach_any_settings_page(): void
    {
        $user = $this->makeUser(InventoryRole::User);

        $this->actingAs($user)->get(ItemCategoryResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(LocationResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(UnitOfMeasureResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(ItemDataPage::getUrl())->assertForbidden();
        $this->actingAs($user)->get(RecipientResource::getUrl('index'))->assertForbidden();
        $this->actingAs($user)->get(SupplierResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_stock_admin_only_viewer_can_reach_only_suppliers_and_recipients(): void
    {
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);

        $this->actingAs($stockAdmin)->get(ItemCategoryResource::getUrl('index'))->assertForbidden();
        $this->actingAs($stockAdmin)->get(LocationResource::getUrl('index'))->assertForbidden();
        $this->actingAs($stockAdmin)->get(UnitOfMeasureResource::getUrl('index'))->assertForbidden();
        $this->actingAs($stockAdmin)->get(ItemDataPage::getUrl())->assertForbidden();

        $this->actingAs($stockAdmin)->get(RecipientResource::getUrl('index'))->assertOk();
        $this->actingAs($stockAdmin)->get(SupplierResource::getUrl('index'))->assertOk();
    }

    public function test_an_admin_can_reach_every_settings_page(): void
    {
        $admin = $this->makeUser(InventoryRole::Admin);

        $this->actingAs($admin)->get(ItemCategoryResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(LocationResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(UnitOfMeasureResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(ItemDataPage::getUrl())->assertOk();
        $this->actingAs($admin)->get(RecipientResource::getUrl('index'))->assertOk();
        $this->actingAs($admin)->get(SupplierResource::getUrl('index'))->assertOk();
    }
}
