<?php

namespace Tests\Feature;

use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Resources\Items\ItemResource;
use App\Filament\Inventory\Resources\Returns\ReturnResource;
use App\Filament\Inventory\Resources\StockMovements\StockMovementResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A plain User's sidebar must be limited to Dashboard, Inventory Items
 * (view-only — see ItemResource's own canCreate()/canEdit()), and
 * Stock Out (Issue Requests). Everything else operational (Stock In,
 * Returns, Adjustments, Movements) is Stock-Admin-or-above, except
 * Adjustments which Approvers also need in order to review and act on
 * ones pending their approval.
 */
class InventoryRoleNavigationTest extends TestCase
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

    public function test_a_plain_user_can_only_reach_items_and_issue_requests(): void
    {
        $user = $this->makeUser(InventoryRole::User);
        $this->actingAs($user);

        $this->assertTrue(ItemResource::canAccess());
        $this->assertTrue(IssueRequestResource::canAccess());

        $this->assertFalse(GoodsReceiptResource::canAccess());
        $this->assertFalse(ReturnResource::canAccess());
        $this->assertFalse(AdjustmentResource::canAccess());
        $this->assertFalse(StockMovementResource::canAccess());
    }

    public function test_a_plain_user_cannot_create_or_edit_items(): void
    {
        $user = $this->makeUser(InventoryRole::User);
        $this->actingAs($user);

        $this->assertFalse(ItemResource::canCreate());
    }

    public function test_an_approver_only_viewer_can_still_reach_adjustments_but_not_the_other_stock_admin_resources(): void
    {
        $approver = $this->makeUser(InventoryRole::Approver);
        $this->actingAs($approver);

        $this->assertTrue(AdjustmentResource::canAccess());

        $this->assertFalse(GoodsReceiptResource::canAccess());
        $this->assertFalse(ReturnResource::canAccess());
        $this->assertFalse(StockMovementResource::canAccess());
    }

    public function test_a_stock_admin_can_reach_every_operational_resource(): void
    {
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $this->actingAs($stockAdmin);

        $this->assertTrue(ItemResource::canAccess());
        $this->assertTrue(IssueRequestResource::canAccess());
        $this->assertTrue(GoodsReceiptResource::canAccess());
        $this->assertTrue(ReturnResource::canAccess());
        $this->assertTrue(AdjustmentResource::canAccess());
        $this->assertTrue(StockMovementResource::canAccess());
    }
}
