<?php

namespace Tests\Feature;

use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryRole;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryRecipient;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Notifications\InventoryAlert;
use App\Services\Inventory\StockMovementService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
    }

    private function makeRecipient(): InventoryRecipient
    {
        return InventoryRecipient::create(['name' => 'Jane Recipient']);
    }

    private function makeUser(InventoryRole $role): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => $role]);

        return $user;
    }

    private function makeItem(string $code, string $reorderLevel = '0'): InventoryItem
    {
        $category = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $item = InventoryItem::create([
            'code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'uom_id' => $uom->id,
            'reorder_level' => $reorderLevel,
        ]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        return $item;
    }

    private function invoke(Action $action, InventoryIssueRequest $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(['lines.item', 'location']), $data);
    }

    public function test_submitting_a_request_notifies_every_approver_but_not_the_submitter(): void
    {
        $requester = $this->makeUser(InventoryRole::Approver); // holds Approver too, to prove self-exclusion
        $otherApprover = $this->makeUser(InventoryRole::Approver);
        $item = $this->makeItem('STAT-0001');

        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'recipient_id' => $this->makeRecipient()->id, 'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Draft,
        ]);
        $request->lines()->create(['line_no' => 1, 'item_id' => $item->id, 'requested_qty' => '1']);

        $this->actingAs($requester);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        $this->assertSame(0, $requester->notifications()->count());
        $this->assertSame(1, $otherApprover->notifications()->count());
    }

    /**
     * The mail channel is a separate assertion from the in-app one
     * above — Notification::fake() intercepts $user->notify() calls
     * uniformly, which is also how Filament's own sendToDatabase()
     * dispatches (see DatabaseNotification, a real
     * Illuminate\Notifications\Notification under the hood), so faking
     * to check mail would silently suppress the real database rows the
     * other test checks for.
     */
    public function test_submitting_a_request_emails_the_approver(): void
    {
        Notification::fake();

        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $item = $this->makeItem('STAT-0001');

        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'recipient_id' => $this->makeRecipient()->id, 'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Draft,
        ]);
        $request->lines()->create(['line_no' => 1, 'item_id' => $item->id, 'requested_qty' => '1']);

        $this->actingAs($requester);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        Notification::assertSentTo($approver, InventoryAlert::class);
        Notification::assertNotSentTo($requester, InventoryAlert::class);
    }

    public function test_approving_a_request_notifies_the_requester_not_the_approver(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $item = $this->makeItem('STAT-0001');
        $item->stock()->update(['on_hand' => '10']); // reserve() needs available stock to succeed

        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-0001', 'request_date' => now(), 'requested_by' => $requester->id,
            'location_id' => $this->location->id, 'recipient_id' => $this->makeRecipient()->id, 'purpose' => 'Test', 'status' => InventoryIssueRequestStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $line = $request->lines()->create(['line_no' => 1, 'item_id' => $item->id, 'requested_qty' => '1']);

        $this->actingAs($approver);
        $this->invoke(IssueRequestResource::approveAction(), $request, [
            'lines' => [['line_id' => $line->id, 'approved_qty' => '1']],
        ]);

        $this->assertSame(1, $requester->notifications()->count());
        $this->assertSame(0, $approver->notifications()->count());
    }

    public function test_an_item_crossing_into_low_stock_notifies_stock_admins_once_not_per_movement(): void
    {
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $performer = User::factory()->create();
        $item = $this->makeItem('STAT-0001', reorderLevel: '10');
        $movements = app(StockMovementService::class);

        $this->actingAs($performer);

        // 100 -> 8: crosses OK -> Low/Critical in one movement.
        $movements->record($item, $this->location, InventoryMovementType::Opening, '100', $performer, InventorySourceType::Opening);
        $this->assertSame(0, $stockAdmin->notifications()->count(), 'receiving well above the reorder level should not notify');

        $movements->record($item, $this->location, InventoryMovementType::AdjustOut, '92', $performer, InventorySourceType::Adjustment);
        $this->assertSame(1, $stockAdmin->notifications()->count());

        // Another small movement that keeps it in the same bucket
        // (still > 0, still <= reorder level) must not notify again.
        $movements->record($item, $this->location, InventoryMovementType::AdjustOut, '1', $performer, InventorySourceType::Adjustment);
        $this->assertSame(1, $stockAdmin->notifications()->count(), 'staying in the same severity bucket should not re-notify');

        // Dropping further into a worse bucket (Out of stock) does notify again.
        $movements->record($item, $this->location, InventoryMovementType::AdjustOut, '7', $performer, InventorySourceType::Adjustment);
        $this->assertSame(2, $stockAdmin->notifications()->count());
    }

    public function test_notifications_are_never_sent_to_the_acting_user(): void
    {
        $adminActor = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', reorderLevel: '10');
        $movements = app(StockMovementService::class);

        $this->actingAs($adminActor);
        $movements->record($item, $this->location, InventoryMovementType::Opening, '5', $adminActor, InventorySourceType::Opening);

        $this->assertSame(0, $adminActor->notifications()->count());
    }
}
