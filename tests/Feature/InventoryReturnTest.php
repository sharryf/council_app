<?php

namespace Tests\Feature;

use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryReturnCondition;
use App\Enums\InventoryReturnStatus;
use App\Enums\InventoryRole;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\Returns\ReturnResource;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryRecipient;
use App\Models\InventoryStockMovement;
use App\Models\InventoryStockReturn;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReturnTest extends TestCase
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

    private function issuedRequest(InventoryItem $item, string $issuedQty): InventoryIssueRequest
    {
        $requester = User::factory()->create();
        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-'.str_pad((string) (InventoryIssueRequest::count() + 1), 4, '0', STR_PAD_LEFT),
            'request_date' => now(), 'requested_by' => $requester->id, 'location_id' => $this->location->id,
            'recipient_id' => InventoryRecipient::create(['name' => 'Jane Recipient'])->id,
            'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Issued,
        ]);
        $request->lines()->create([
            'line_no' => 1, 'item_id' => $item->id, 'requested_qty' => $issuedQty,
            'approved_qty' => $issuedQty, 'issued_qty' => $issuedQty, 'returned_qty' => 0,
        ]);

        return $request;
    }

    private function draftReturn(array $lines, array $overrides = []): InventoryStockReturn
    {
        $return = InventoryStockReturn::create([
            'return_no' => 'RET-2026-'.str_pad((string) (InventoryStockReturn::count() + 1), 4, '0', STR_PAD_LEFT),
            'return_date' => now(), 'returned_by' => User::factory()->create()->id,
            'location_id' => $this->location->id, 'status' => InventoryReturnStatus::Draft,
            ...$overrides,
        ]);

        foreach ($lines as $i => [$item, $qty, $condition, $issueLineId]) {
            $return->lines()->create([
                'line_no' => $i + 1, 'item_id' => $item->id, 'quantity' => $qty,
                'condition' => $condition, 'issue_line_id' => $issueLineId,
            ]);
        }

        return $return;
    }

    private function invoke(Action $action, InventoryStockReturn $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(['lines.item', 'lines.issueLine', 'location']), $data);
    }

    public function test_a_good_line_creates_a_return_movement_and_increases_on_hand(): void
    {
        $admin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '10');
        $return = $this->draftReturn([[$item, '3', InventoryReturnCondition::Good, null]]);

        $this->actingAs($admin);
        $this->invoke(ReturnResource::postAction(), $return);

        $this->assertSame(InventoryReturnStatus::Posted, $return->fresh()->status);
        $this->assertSame('13.000', $item->stock()->sole()->on_hand);
        $movement = InventoryStockMovement::where('source_id', $return->id)->sole();
        $this->assertSame(InventoryMovementType::Return, $movement->movement_type);
    }

    public function test_a_damaged_line_creates_no_movement(): void
    {
        $admin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '10');
        $return = $this->draftReturn([[$item, '3', InventoryReturnCondition::Damaged, null]]);

        $this->actingAs($admin);
        $this->invoke(ReturnResource::postAction(), $return);

        $this->assertSame(InventoryReturnStatus::Posted, $return->fresh()->status);
        $this->assertSame('10.000', $item->stock()->sole()->on_hand);
        $this->assertSame(0, InventoryStockMovement::where('source_id', $return->id)->count());
    }

    public function test_a_return_linked_to_a_request_increments_returned_qty_and_caps_at_the_remaining_balance(): void
    {
        $admin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '0');
        $request = $this->issuedRequest($item, '10');
        $line = $request->lines()->sole();

        $return = $this->draftReturn([[$item, '4', InventoryReturnCondition::Good, $line->id]], ['issue_request_id' => $request->id]);

        $this->actingAs($admin);
        $this->invoke(ReturnResource::postAction(), $return);

        $this->assertSame('4.000', $line->fresh()->returned_qty);

        // A second return attempting more than the remaining 6 must be blocked.
        $overCapped = $this->draftReturn([[$item, '7', InventoryReturnCondition::Good, $line->id]], ['issue_request_id' => $request->id]);
        $this->invoke(ReturnResource::postAction(), $overCapped);

        $this->assertSame(InventoryReturnStatus::Draft, $overCapped->fresh()->status);
        $this->assertSame('4.000', $line->fresh()->returned_qty);
    }

    public function test_reversing_a_return_restores_on_hand_and_decrements_returned_qty(): void
    {
        $admin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '0');
        $request = $this->issuedRequest($item, '10');
        $line = $request->lines()->sole();

        $return = $this->draftReturn([[$item, '4', InventoryReturnCondition::Good, $line->id]], ['issue_request_id' => $request->id]);

        $this->actingAs($admin);
        $this->invoke(ReturnResource::postAction(), $return);
        $this->invoke(ReturnResource::reverseAction(), $return, ['reason' => 'Testing reversal']);

        $this->assertSame(InventoryReturnStatus::Reversed, $return->fresh()->status);
        $this->assertSame('0.000', $item->stock()->sole()->on_hand);
        $this->assertSame('0.000', $line->fresh()->returned_qty);
    }

    public function test_a_plain_user_role_cannot_create_or_manage_returns(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->assertFalse(ReturnResource::canCreate());

        $item = $this->makeItem('STAT-0001');
        $return = $this->draftReturn([[$item, '1', InventoryReturnCondition::Good, null]]);

        $this->assertFalse(ReturnResource::canEdit($return));
        $this->assertFalse(ReturnResource::canDelete($return));
    }
}
