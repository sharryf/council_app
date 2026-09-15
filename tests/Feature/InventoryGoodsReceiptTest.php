<?php

namespace Tests\Feature;

use App\Enums\InventoryGoodsReceiptStatus;
use App\Enums\InventoryRole;
use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Inventory\Resources\GoodsReceipts\Pages\ListGoodsReceipts;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;
use App\Models\InventorySetting;
use App\Models\InventorySupplier;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryGoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;

    private InventorySupplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('inventory'));

        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
        $this->supplier = InventorySupplier::create(['code' => 'SUP1', 'name' => 'Acme Supplies']);
    }

    private function makeStockAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::StockAdmin]);

        return $user;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::Admin]);

        return $user;
    }

    private function makeItem(string $code): InventoryItem
    {
        $category = InventoryItemCategory::query()->firstOrCreate(['code' => 'STAT'], ['name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::query()->firstOrCreate(['code' => 'PC'], ['name' => 'Piece', 'decimal_places' => 0]);

        $item = InventoryItem::create(['code' => $code, 'name' => "Item {$code}", 'category_id' => $category->id, 'uom_id' => $uom->id]);
        $item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        return $item;
    }

    private function draftGrn(array $lines, array $overrides = []): InventoryGoodsReceipt
    {
        $grn = InventoryGoodsReceipt::create([
            'grn_no' => 'GRN-2026-'.str_pad((string) (InventoryGoodsReceipt::count() + 1), 4, '0', STR_PAD_LEFT),
            'receipt_date' => now(),
            'location_id' => $this->location->id,
            'supplier_id' => $this->supplier->id,
            'status' => InventoryGoodsReceiptStatus::Draft,
            ...$overrides,
        ]);

        foreach ($lines as $i => [$item, $quantity]) {
            $grn->lines()->create(['line_no' => $i + 1, 'item_id' => $item->id, 'quantity' => $quantity]);
        }

        return $grn;
    }

    public function test_posting_a_five_line_grn_produces_five_movements_and_correct_balances(): void
    {
        $admin = $this->makeStockAdmin();
        $items = collect(range(1, 5))->map(fn ($i) => $this->makeItem("STAT-000{$i}"));

        $grn = $this->draftGrn($items->map(fn ($item) => [$item, '10'])->all(), ['invoice_no' => 'INV-1']);

        $this->actingAs($admin);
        $this->invokePostAction($grn);

        $grn->refresh();
        $this->assertSame(InventoryGoodsReceiptStatus::Posted, $grn->status);

        foreach ($items as $item) {
            $stock = $item->stock()->sole();
            $this->assertSame('10.000', $stock->on_hand);

            $movement = $item->movements()->sole();
            $this->assertSame('10.000', $movement->balance_after);
        }

        $this->assertSame(5, \App\Models\InventoryStockMovement::query()->where('source_id', $grn->id)->count());
    }

    /**
     * GoodsReceiptResource::postAction()/reverseAction() build Filament
     * Action objects meant to be invoked through the Livewire action
     * lifecycle (mountAction/callMountedAction), which needs a live
     * component. Since these tests care about the business outcome —
     * not Filament's own action-dispatch plumbing, already exercised
     * live in the browser during development — they call the action's
     * ->action() closure directly against the record.
     */
    private function invokePostAction(InventoryGoodsReceipt $grn): void
    {
        $action = GoodsReceiptResource::postAction();
        $callback = (fn () => $this->action)->call($action);
        $callback($grn->fresh(['lines.item', 'location']));
    }

    private function invokeRequestReversalAction(InventoryGoodsReceipt $grn, string $reason): void
    {
        $action = GoodsReceiptResource::requestReversalAction();
        $callback = (fn () => $this->action)->call($action);
        $callback($grn->fresh(), ['reason' => $reason]);
    }

    private function invokeApproveReversalAction(InventoryGoodsReceipt $grn): void
    {
        $action = GoodsReceiptResource::approveReversalAction();
        $callback = (fn () => $this->action)->call($action);
        $callback($grn->fresh());
    }

    public function test_a_grn_is_findable_by_its_po_number(): void
    {
        $admin = $this->makeStockAdmin();
        $item = $this->makeItem('STAT-0001');
        $this->draftGrn([[$item, '10']], ['po_no' => 'PO-2026-114']);
        $this->draftGrn([[$item, '5']], ['po_no' => 'PO-OTHER']);

        $this->actingAs($admin);

        Livewire::test(ListGoodsReceipts::class)
            ->searchTable('PO-2026-114')
            ->assertCanSeeTableRecords(InventoryGoodsReceipt::where('po_no', 'PO-2026-114')->get())
            ->assertCanNotSeeTableRecords(InventoryGoodsReceipt::where('po_no', 'PO-OTHER')->get());
    }

    public function test_a_posted_grn_cannot_be_edited(): void
    {
        $item = $this->makeItem('STAT-0001');
        $grn = $this->draftGrn([[$item, '10']], ['invoice_no' => 'INV-1', 'status' => InventoryGoodsReceiptStatus::Posted]);

        $this->assertFalse(GoodsReceiptResource::canEdit($grn));
        $this->assertFalse(GoodsReceiptResource::canDelete($grn));
    }

    public function test_reversing_a_grn_requires_approval_before_it_restores_balances(): void
    {
        $stockAdmin = $this->makeStockAdmin();
        $admin = $this->makeAdmin();
        $item = $this->makeItem('STAT-0001');
        $grn = $this->draftGrn([[$item, '40']], ['invoice_no' => 'INV-1']);

        $this->actingAs($stockAdmin);
        $this->invokePostAction($grn);
        $this->invokeRequestReversalAction($grn, 'Testing reversal');

        // Requesting alone must not touch stock yet.
        $this->assertSame(InventoryGoodsReceiptStatus::PendingReversal, $grn->fresh()->status);
        $this->assertSame('40.000', $item->stock()->sole()->on_hand);

        $this->actingAs($admin);
        $this->invokeApproveReversalAction($grn);

        $grn->refresh();
        $this->assertSame(InventoryGoodsReceiptStatus::Reversed, $grn->status);
        $this->assertSame('0.000', $item->stock()->sole()->on_hand);
    }

    public function test_reversal_approval_is_blocked_and_rolled_back_if_any_line_would_go_negative(): void
    {
        $stockAdmin = $this->makeStockAdmin();
        $admin = $this->makeAdmin();
        $itemA = $this->makeItem('STAT-0001');
        $itemB = $this->makeItem('STAT-0002');
        $grn = $this->draftGrn([[$itemA, '40'], [$itemB, '40']], ['invoice_no' => 'INV-1']);

        $this->actingAs($stockAdmin);
        $this->invokePostAction($grn);

        // Issue out all of itemB's stock via a second, independent
        // movement so reversing the GRN would push itemB negative.
        app(\App\Services\Inventory\StockMovementService::class)->record(
            item: $itemB,
            location: $this->location,
            type: \App\Enums\InventoryMovementType::Issue,
            quantity: '40',
            performer: $stockAdmin,
            sourceType: \App\Enums\InventorySourceType::Adjustment,
        );

        $this->invokeRequestReversalAction($grn, 'Should fail');

        $this->actingAs($admin);
        $this->invokeApproveReversalAction($grn);

        // Blocked entirely — the GRN stays PendingReversal (an admin
        // still needs to resolve it, by rejecting or retrying once the
        // underlying issue is fixed), and itemA's balance (reversed
        // first, before itemB failed) must have rolled back too, not
        // been left half-reversed.
        $this->assertSame(InventoryGoodsReceiptStatus::PendingReversal, $grn->fresh()->status);
        $this->assertSame('40.000', $itemA->stock()->sole()->on_hand);
    }

    public function test_require_receipt_reference_blocks_posting_with_no_reference(): void
    {
        InventorySetting::create(['key' => 'require_receipt_reference', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'receipt']);

        $admin = $this->makeStockAdmin();
        $item = $this->makeItem('STAT-0001');
        $grn = $this->draftGrn([[$item, '10']]); // no invoice/po/delivery-note

        $this->actingAs($admin);
        $this->invokePostAction($grn);

        $this->assertSame(InventoryGoodsReceiptStatus::Draft, $grn->fresh()->status);
        $this->assertSame(0, $item->movements()->count());
    }

    /**
     * A supplier legitimately resending the same invoice number is a
     * real scenario — the old unique DB constraint hard-blocked it with
     * a raw SQL error, bypassing the (never-actually-read)
     * 'allow_duplicate_invoice' setting entirely. It's now allowed to
     * save; GoodsReceiptForm::duplicateInvoiceWarning() flags it to the
     * storekeeper as a soft warning instead (covered by a Filament
     * hint, not asserted here — see that method's own docblock).
     */
    public function test_duplicate_invoice_for_the_same_supplier_is_allowed_to_save(): void
    {
        InventoryGoodsReceipt::create([
            'grn_no' => 'GRN-2026-0001', 'receipt_date' => now(), 'location_id' => $this->location->id,
            'supplier_id' => $this->supplier->id, 'invoice_no' => 'INV-DUP', 'status' => InventoryGoodsReceiptStatus::Draft,
        ]);

        $second = InventoryGoodsReceipt::create([
            'grn_no' => 'GRN-2026-0002', 'receipt_date' => now(), 'location_id' => $this->location->id,
            'supplier_id' => $this->supplier->id, 'invoice_no' => 'INV-DUP', 'status' => InventoryGoodsReceiptStatus::Draft,
        ]);

        $this->assertTrue($second->exists);
    }

    /**
     * The Create modal itself (not just the DB layer) must not block a
     * duplicate invoice either — GoodsReceiptForm's hint is a warning
     * only, so submitting must still succeed.
     */
    public function test_the_create_form_saves_successfully_despite_a_duplicate_invoice(): void
    {
        $this->draftGrn([], ['grn_no' => 'GRN-2026-EXISTING', 'invoice_no' => 'INV-DUP']);

        $item = $this->makeItem('STAT-0001');
        $this->actingAs($this->makeStockAdmin());

        Livewire::test(ListGoodsReceipts::class)
            ->mountAction('create')
            ->fillForm([
                'receipt_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'invoice_no' => 'INV-DUP',
                'lines' => [['item_id' => $item->id, 'quantity' => '5']],
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(2, InventoryGoodsReceipt::where('invoice_no', 'INV-DUP')->count());
    }

    public function test_the_create_form_rejects_an_attachment_over_the_configured_size_limit(): void
    {
        InventorySetting::create(['key' => 'max_attachment_mb', 'value' => '5', 'data_type' => 'integer', 'category' => 'attachment']);
        Storage::fake('local');

        $item = $this->makeItem('STAT-0001');
        $this->actingAs($this->makeStockAdmin());

        Livewire::test(ListGoodsReceipts::class)
            ->mountAction('create')
            ->fillForm([
                'receipt_date' => now()->toDateString(),
                'supplier_id' => $this->supplier->id,
                'lines' => [['item_id' => $item->id, 'quantity' => '5']],
                'attachments' => [UploadedFile::fake()->create('invoice.pdf', 6000)],
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['attachments']);
    }

    public function test_posting_updates_item_last_received_date_and_default_supplier(): void
    {
        $admin = $this->makeStockAdmin();
        $item = $this->makeItem('STAT-0001');
        $this->assertNull($item->default_supplier_id);

        $grn = $this->draftGrn([[$item, '10']], ['invoice_no' => 'INV-1']);

        $this->actingAs($admin);
        $this->invokePostAction($grn);

        $item->refresh();
        $this->assertSame($this->supplier->id, $item->default_supplier_id);
        $this->assertTrue($item->last_received_date->isToday());
    }

    public function test_a_plain_user_role_cannot_create_or_manage_goods_receipts(): void
    {
        $user = User::factory()->create();
        $user->inventoryRoles()->create(['role' => InventoryRole::User]);

        $this->assertFalse(GoodsReceiptResource::canCreate());

        $item = $this->makeItem('STAT-0001');
        $grn = $this->draftGrn([[$item, '10']]);

        $this->assertFalse(GoodsReceiptResource::canEdit($grn));
        $this->assertFalse(GoodsReceiptResource::canDelete($grn));
    }
}
