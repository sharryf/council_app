<?php

namespace Tests\Feature;

use App\Enums\InventoryIssueRequestLineStatus;
use App\Enums\InventoryIssueRequestStatus;
use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Resources\IssueRequests\Pages\IssueGoods;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventoryRecipient;
use App\Models\InventorySetting;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryIssueRequestTest extends TestCase
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

    /**
     * Seeds the starting balance through a real Opening movement (not a
     * direct create()) so reconcileDrift() stays clean for tests that
     * check it — a directly-set on_hand with no ledger entry behind it
     * is exactly the kind of drift that helper is meant to catch.
     */
    private function makeItem(string $code, string $onHand = '0'): InventoryItem
    {
        $category = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $item = InventoryItem::create(['code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'uom_id' => $uom->id]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        if (bccomp($onHand, '0', 3) > 0) {
            app(StockMovementService::class)->record(
                item: $item,
                location: $this->location,
                type: \App\Enums\InventoryMovementType::Opening,
                quantity: $onHand,
                performer: User::factory()->create(),
                sourceType: \App\Enums\InventorySourceType::Opening,
            );
        }

        return $item;
    }

    private function makeRecipient(): InventoryRecipient
    {
        return InventoryRecipient::create(['name' => 'Jane Recipient']);
    }

    private function draftRequest(User $requester, array $lines, array $overrides = []): InventoryIssueRequest
    {
        $request = InventoryIssueRequest::create([
            'request_no' => 'IR-2026-'.str_pad((string) (InventoryIssueRequest::count() + 1), 4, '0', STR_PAD_LEFT),
            'request_date' => now(),
            'requested_by' => $requester->id,
            'location_id' => $this->location->id,
            'recipient_id' => $this->makeRecipient()->id,
            'purpose' => 'Office supplies',
            'status' => InventoryIssueRequestStatus::Draft,
            ...$overrides,
        ]);

        foreach ($lines as $i => [$item, $qty]) {
            $request->lines()->create(['line_no' => $i + 1, 'item_id' => $item->id, 'requested_qty' => $qty]);
        }

        return $request;
    }

    private function invoke(Action $action, InventoryIssueRequest $record, array $data = []): void
    {
        $callback = (fn () => $this->action)->call($action);
        $callback($record->fresh(['lines.item', 'location']), $data);
    }

    private function submitAndApprove(InventoryIssueRequest $request, User $approver, array $approvedQtyByLineId = []): void
    {
        $this->actingAs($request->requester);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        $this->actingAs($approver);
        $lines = collect($approvedQtyByLineId ?: $request->lines()->pluck('requested_qty', 'id'))
            ->map(fn ($qty, $lineId) => ['line_id' => $lineId, 'approved_qty' => (string) $qty])
            ->values()
            ->all();

        $this->invoke(IssueRequestResource::approveAction(), $request, ['lines' => $lines]);
    }

    public function test_approving_reserves_stock_without_touching_on_hand(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '30']]);

        $this->submitAndApprove($request, $approver);

        $stock = $item->stock()->sole();
        $this->assertSame('100.000', $stock->on_hand);
        $this->assertSame('30.000', $stock->reserved);
        $this->assertSame(InventoryIssueRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_partial_issue_updates_reserved_issued_and_on_hand(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);

        $this->submitAndApprove($request, $approver);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);
        $line = $request->lines()->sole();
        $page->issueQuantities[$line->id] = '6';
        $page->submitIssue();

        $stock = $item->stock()->sole();
        $this->assertSame('94.000', $stock->on_hand);
        $this->assertSame('4.000', $stock->reserved);
        $this->assertSame(InventoryIssueRequestStatus::PartiallyIssued, $request->fresh()->status);
        $this->assertSame(InventoryIssueRequestLineStatus::PartiallyIssued, $line->fresh()->line_status);

        // Issue the remainder on a second trip — the done-when bar: a
        // full draft -> submit -> approve -> partial issue -> complete
        // issue cycle leaves stock exactly right.
        $page2 = new IssueGoods;
        $page2->mount($request->id);
        $page2->issueQuantities[$line->id] = '4';
        $page2->submitIssue();

        $stock = $item->stock()->sole();
        $this->assertSame('90.000', $stock->on_hand);
        $this->assertSame('0.000', $stock->reserved);
        $this->assertSame(InventoryIssueRequestStatus::Issued, $request->fresh()->status);
        $this->assertSame(InventoryIssueRequestLineStatus::Issued, $line->fresh()->line_status);

        $this->assertCount(0, app(StockMovementService::class)->reconcileDrift());
    }

    public function test_cancelling_a_partially_issued_request_releases_remaining_reservation(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);

        $this->submitAndApprove($request, $approver);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);
        $line = $request->lines()->sole();
        $page->issueQuantities[$line->id] = '6';
        $page->submitIssue();

        $this->invoke(IssueRequestResource::cancelAction(), $request->fresh(), ['reason' => 'No longer needed']);

        $stock = $item->stock()->sole();
        $this->assertSame('0.000', $stock->reserved);
        $this->assertSame(InventoryIssueRequestStatus::Cancelled, $request->fresh()->status);
    }

    public function test_approver_cannot_approve_own_request_when_self_approval_disabled(): void
    {
        InventorySetting::query()->updateOrCreate(['key' => 'allow_self_approval'], ['value' => 'false', 'data_type' => 'boolean', 'category' => 'approval']);

        $approver = $this->makeUser(InventoryRole::Approver);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($approver, [[$item, '10']]);

        $this->actingAs($approver);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        $this->assertFalse(IssueRequestResource::approveAction()->record($request->fresh())->isVisible());
    }

    public function test_plain_user_can_create_and_submit_but_not_approve_or_issue(): void
    {
        $user = $this->makeUser(InventoryRole::User);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($user, [[$item, '10']]);

        $this->actingAs($user);
        $this->assertTrue(IssueRequestResource::canCreate());

        $this->invoke(IssueRequestResource::submitAction(), $request);
        $this->assertSame(InventoryIssueRequestStatus::Submitted, $request->fresh()->status);

        $this->assertFalse(IssueRequestResource::approveAction()->record($request->fresh())->isVisible());
        $this->assertFalse(IssueRequestResource::canIssue($request->fresh()));
    }

    /**
     * submitIssue() catches its own InvalidArgumentException and shows
     * a Notification rather than letting it bubble up (same pattern as
     * GoodsReceiptResource's post/reverse actions), so the outcome to
     * assert is that nothing committed — issued_qty stays untouched —
     * not a thrown exception.
     */
    public function test_approving_a_reduced_quantity_caps_what_can_be_issued(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);
        $line = $request->lines()->sole();

        $this->submitAndApprove($request, $approver, [$line->id => '5']);

        $this->assertSame('5.000', $line->fresh()->approved_qty);
        $this->assertSame('5.000', $item->stock()->sole()->reserved);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);
        $page->issueQuantities[$line->id] = '10';
        $page->submitIssue();

        $this->assertSame('0.000', $line->fresh()->issued_qty);
        $this->assertSame('5.000', $item->stock()->sole()->reserved);
        $this->assertSame(InventoryIssueRequestStatus::Approved, $request->fresh()->status);
    }

    public function test_require_approval_for_issue_off_lets_stock_admin_issue_directly_from_submitted(): void
    {
        InventorySetting::query()->updateOrCreate(['key' => 'require_approval_for_issue'], ['value' => 'false', 'data_type' => 'boolean', 'category' => 'approval']);

        $requester = $this->makeUser(InventoryRole::User);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);
        $line = $request->lines()->sole();

        $this->actingAs($requester);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        $this->actingAs($stockAdmin);
        $this->assertTrue(IssueRequestResource::canIssue($request->fresh()));

        $page = new IssueGoods;
        $page->mount($request->id);
        $page->issueQuantities[$line->id] = '10';
        $page->submitIssue();

        $this->assertSame('90.000', $item->stock()->sole()->on_hand);
        $this->assertSame(InventoryIssueRequestStatus::Issued, $request->fresh()->status);
    }

    /**
     * Issuing To is optional — most requests are the requester
     * collecting for themselves, not on someone else's behalf. When no
     * recipient was picked, IssueGoods should pre-fill the receiver
     * name with the requester's own name rather than leaving it blank.
     */
    public function test_issue_goods_prefills_receiver_name_from_requester_when_no_recipient_chosen(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']], ['recipient_id' => null]);

        InventorySetting::query()->updateOrCreate(['key' => 'require_approval_for_issue'], ['value' => 'false', 'data_type' => 'boolean', 'category' => 'approval']);

        $this->actingAs($requester);
        $this->invoke(IssueRequestResource::submitAction(), $request);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);

        $this->assertSame($requester->name, $page->receivedByName);
    }

    /**
     * When a request is issued to two different people across two
     * visits, each collector must keep their own record — the second
     * person's signature/name must never erase the first's. Each
     * InventoryIssueReceipt should only account for the quantity
     * actually handed over in that specific batch, not the running
     * total.
     */
    public function test_issuing_to_two_different_people_keeps_both_receipts_separate(): void
    {
        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);
        $line = $request->lines()->sole();

        $this->submitAndApprove($request, $approver);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);
        $page->receivedByName = 'Ahmed Nashid';
        $page->issueQuantities[$line->id] = '6';
        $page->submitIssue();

        $page2 = new IssueGoods;
        $page2->mount($request->id);
        $page2->receivedByName = 'Fathimath Zeena';
        $page2->issueQuantities[$line->id] = '4';
        $page2->submitIssue();

        $request->refresh();
        $this->assertCount(2, $request->receipts);

        $firstReceipt = $request->receipts->first();
        $secondReceipt = $request->receipts->last();

        $this->assertSame('Ahmed Nashid', $firstReceipt->received_by_name);
        $this->assertSame('6.000', (string) $firstReceipt->movements->sole()->quantity);

        $this->assertSame('Fathimath Zeena', $secondReceipt->received_by_name);
        $this->assertSame('4.000', (string) $secondReceipt->movements->sole()->quantity);

        // The parent row is a last-known mirror only — it reflects
        // whoever collected most recently, never the full history.
        $this->assertSame('Fathimath Zeena', $request->received_by_name);
    }

    /**
     * When signature capture is enabled, IssueGoods must refuse to
     * submit without one — nothing should commit (no movement, no
     * receipt, status unchanged) until a signature is actually drawn.
     */
    public function test_signature_is_required_before_issuing_when_capture_is_enabled(): void
    {
        InventorySetting::query()->updateOrCreate(['key' => 'enable_signature_capture'], ['value' => 'true', 'data_type' => 'boolean', 'category' => 'request']);

        $requester = $this->makeUser(InventoryRole::User);
        $approver = $this->makeUser(InventoryRole::Approver);
        $stockAdmin = $this->makeUser(InventoryRole::StockAdmin);
        $item = $this->makeItem('STAT-0001', '100');
        $request = $this->draftRequest($requester, [[$item, '10']]);
        $line = $request->lines()->sole();

        $this->submitAndApprove($request, $approver);

        $this->actingAs($stockAdmin);
        $page = new IssueGoods;
        $page->mount($request->id);
        $page->issueQuantities[$line->id] = '10';
        $page->submitIssue();

        $request->refresh();
        $this->assertSame(InventoryIssueRequestStatus::Approved, $request->status);
        $this->assertCount(0, $request->receipts);
        $this->assertSame('100.000', $item->stock()->sole()->on_hand);

        // Now draw one and confirm it goes through.
        $page->signatureDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $page->submitIssue();

        $request->refresh();
        $this->assertSame(InventoryIssueRequestStatus::Issued, $request->status);
        $this->assertCount(1, $request->receipts);
    }
}
