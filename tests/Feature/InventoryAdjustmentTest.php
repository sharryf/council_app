<?php

namespace Tests\Feature;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryRole;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryStockAdjustment;
use App\Models\InventoryStockMovement;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
    }

    private function makeUser(InventoryRole $role): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => $role]);

        return $user;
    }

    private function makeItem(string $code, string $onHand = '0'): InventoryItem
    {
        $category = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $item = InventoryItem::create(['code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'uom_id' => $uom->id]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        if (bccomp($onHand, '0', 3) > 0) {
            app(StockMovementService::class)->record(
                item: $item, location: $this->location, type: InventoryMovementType::Opening,
                quantity: $onHand, performer: User::factory()->create(), sourceType: InventorySourceType::Opening,
            );
        }

        return $item;
    }

    private function draftAdjustment(InventoryAdjustmentType $type, array $lines, array $overrides = []): InventoryStockAdjustment
    {
        $adjustment = InventoryStockAdjustment::create([
            'adjustment_no' => 'ADJ-2026-'.str_pad((string) (InventoryStockAdjustment::count() + 1), 4, '0', STR_PAD_LEFT),
            'adjustment_date' => now(),
            'location_id' => $this->location->id,
            'adjustment_type' => $type,
            'reason' => 'Test adjustment',
            'status' => InventoryAdjustmentStatus::Draft,
            ...$overrides,
        ]);

        foreach ($lines as $i => [$item, $systemQty, $countedQty]) {
            $adjustment->lines()->create([
                'line_no' => $i + 1, 'item_id' => $item->id,
                'system_qty' => $systemQty, 'counted_qty' => $countedQty,
                'difference_qty' => bcsub($countedQty, $systemQty, 3),
            ]);
        }

        return $adjustment;
    }

    private function invoke(Action $action, InventoryStockAdjustment $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(['lines.item', 'location']), $data);
    }

    /**
     * Full lifecycle: the creator only creates and submits — approving
     * is what actually posts, in one step, as a different person.
     */
    private function submitAndApprove(InventoryStockAdjustment $adjustment, User $creator, User $approver): void
    {
        $this->actingAs($creator);
        $this->invoke(AdjustmentResource::submitForApprovalAction(), $adjustment);
        $this->assertSame(InventoryAdjustmentStatus::PendingApproval, $adjustment->fresh()->status);

        $this->actingAs($approver);
        $this->invoke(AdjustmentResource::approveAction(), $adjustment);
    }

    public function test_approving_a_stock_take_writes_only_non_zero_difference_lines_and_sets_last_counted_at(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $approver = $this->makeUser(InventoryRole::Admin);
        $itemA = $this->makeItem('STAT-0001', '100');
        $itemB = $this->makeItem('STAT-0002', '50');

        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::StockTake, [
            [$itemA, '100', '95'],  // -5
            [$itemB, '50', '50'],   // zero diff, ignored
        ], ['created_by' => $creator->id]);

        $this->submitAndApprove($adjustment, $creator, $approver);

        $this->assertSame(InventoryAdjustmentStatus::Posted, $adjustment->fresh()->status);
        $this->assertSame('95.000', $itemA->stock()->sole()->on_hand);
        $this->assertSame('50.000', $itemB->stock()->sole()->on_hand);
        $this->assertNotNull($itemA->stock()->sole()->last_counted_at);

        $this->assertSame(1, InventoryStockMovement::where('source_id', $adjustment->id)->count());
        $movement = InventoryStockMovement::where('source_id', $adjustment->id)->sole();
        $this->assertSame(InventoryMovementType::AdjustOut, $movement->movement_type);
        $this->assertSame('5.000', $movement->quantity);

        $adjustment->refresh();
        $this->assertSame($approver->id, $adjustment->approved_by);
        $this->assertSame($approver->id, $adjustment->posted_by);
        $this->assertNotNull($adjustment->approved_at);
        $this->assertNotNull($adjustment->posted_at);
    }

    public function test_a_positive_difference_posts_as_adjust_in(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $approver = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '10');

        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::StockTake, [[$item, '10', '15']], ['created_by' => $creator->id]);

        $this->submitAndApprove($adjustment, $creator, $approver);

        $this->assertSame('15.000', $item->stock()->sole()->on_hand);
        $this->assertSame(InventoryMovementType::AdjustIn, InventoryStockMovement::where('source_id', $adjustment->id)->sole()->movement_type);
    }

    public function test_every_adjustment_type_goes_through_submit_for_approval_before_it_can_post(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $approver = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '10');

        foreach ([InventoryAdjustmentType::Expiry, InventoryAdjustmentType::OpeningBalance, InventoryAdjustmentType::Damage, InventoryAdjustmentType::StockTake] as $type) {
            $adjustment = $this->draftAdjustment($type, [[$item, '10', '8']], ['created_by' => $creator->id]);

            $this->submitAndApprove($adjustment, $creator, $approver);

            $this->assertSame(InventoryAdjustmentStatus::Posted, $adjustment->fresh()->status);
        }
    }

    public function test_the_creator_cannot_also_approve_the_adjustment(): void
    {
        $creator = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '20');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::Damage, [[$item, '20', '18']], ['created_by' => $creator->id]);

        $this->actingAs($creator);
        $this->invoke(AdjustmentResource::submitForApprovalAction(), $adjustment);
        $adjustment->refresh();

        // The creator also holds Admin, so they *could* approve by
        // role — but segregation of duties must still refuse them.
        $this->assertFalse(AdjustmentResource::approveAction()->record($adjustment)->isVisible());

        $this->invoke(AdjustmentResource::approveAction(), $adjustment);

        $adjustment->refresh();
        $this->assertSame(InventoryAdjustmentStatus::PendingApproval, $adjustment->status);
        $this->assertNull($adjustment->approved_at);
        $this->assertNull($adjustment->posted_at);
        $this->assertSame('20.000', $item->stock()->sole()->on_hand);
        $this->assertSame(0, InventoryStockMovement::where('source_id', $adjustment->id)->count());
    }

    /**
     * Reject is an admin reviewing someone else's work — a creator
     * shouldn't see it on their own pending adjustment (it read as if
     * someone else could decline it when no one else had). cancelAction()
     * is their own equivalent instead: no reason required, since you're
     * not being told why your own submission was declined.
     */
    public function test_the_creator_cannot_reject_their_own_adjustment_but_can_cancel_it(): void
    {
        $creator = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '20');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::Damage, [[$item, '20', '18']], ['created_by' => $creator->id]);

        $this->actingAs($creator);
        $this->invoke(AdjustmentResource::submitForApprovalAction(), $adjustment);
        $adjustment->refresh();

        $this->assertFalse(AdjustmentResource::rejectAction()->record($adjustment)->isVisible());
        $this->assertTrue(AdjustmentResource::cancelAction()->record($adjustment)->isVisible());

        $this->invoke(AdjustmentResource::cancelAction(), $adjustment);

        $this->assertSame(InventoryAdjustmentStatus::Draft, $adjustment->fresh()->status);
    }

    public function test_a_different_admin_can_reject_but_not_cancel_someone_elses_adjustment(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $reviewer = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '20');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::Damage, [[$item, '20', '18']], ['created_by' => $creator->id]);

        $this->actingAs($creator);
        $this->invoke(AdjustmentResource::submitForApprovalAction(), $adjustment);
        $adjustment->refresh();

        $this->actingAs($reviewer);
        $this->assertTrue(AdjustmentResource::rejectAction()->record($adjustment)->isVisible());
        $this->assertFalse(AdjustmentResource::cancelAction()->record($adjustment)->isVisible());

        $this->invoke(AdjustmentResource::rejectAction(), $adjustment, ['reason' => 'Needs rework']);

        $this->assertSame(InventoryAdjustmentStatus::Draft, $adjustment->fresh()->status);
    }

    public function test_a_different_admin_can_approve_and_post_in_one_step(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $approver = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '20');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::Damage, [[$item, '20', '18']], ['created_by' => $creator->id]);

        $this->submitAndApprove($adjustment, $creator, $approver);

        $this->assertSame(InventoryAdjustmentStatus::Posted, $adjustment->fresh()->status);
        $this->assertSame('18.000', $item->stock()->sole()->on_hand);
    }

    public function test_reversing_an_adjustment_restores_the_balance(): void
    {
        $creator = $this->makeUser(InventoryRole::StockAdmin);
        $approver = $this->makeUser(InventoryRole::Admin);
        $item = $this->makeItem('STAT-0001', '100');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::StockTake, [[$item, '100', '90']], ['created_by' => $creator->id]);

        $this->submitAndApprove($adjustment, $creator, $approver);
        $this->invoke(AdjustmentResource::reverseAction(), $adjustment, ['reason' => 'Testing reversal']);

        $this->assertSame(InventoryAdjustmentStatus::Reversed, $adjustment->fresh()->status);
        $this->assertSame('100.000', $item->stock()->sole()->on_hand);
    }

    public function test_a_plain_user_role_cannot_create_or_manage_adjustments(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->assertFalse(AdjustmentResource::canCreate());

        $item = $this->makeItem('STAT-0001');
        $adjustment = $this->draftAdjustment(InventoryAdjustmentType::StockTake, [[$item, '0', '0']]);

        $this->assertFalse(AdjustmentResource::canEdit($adjustment));
        $this->assertFalse(AdjustmentResource::canDelete($adjustment));
    }
}
