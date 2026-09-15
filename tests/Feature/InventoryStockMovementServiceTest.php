<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryItemStock;
use App\Models\InventoryLocation;
use App\Models\InventorySetting;
use App\Models\InventoryUnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class InventoryStockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $item;

    private InventoryLocation $location;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $category = InventoryItemCategory::create(['code' => 'STAT', 'name' => 'Stationery']);
        $uom = InventoryUnitOfMeasure::create(['code' => 'PC', 'name' => 'Piece', 'decimal_places' => 0]);
        $this->location = InventoryLocation::create(['code' => 'MAIN', 'name' => 'Main Store', 'is_default' => true]);
        $this->item = InventoryItem::create([
            'code' => 'STAT-0001', 'name' => 'Test item', 'category_id' => $category->id, 'uom_id' => $uom->id,
        ]);
        $this->item->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);
        $this->user = User::factory()->create();
    }

    private function service(): StockMovementService
    {
        return app(StockMovementService::class);
    }

    /**
     * Thin wrapper around record() defaulting to an Adjustment source —
     * these tests exercise the ledger mechanics directly rather than
     * any particular document workflow (GRN/Issue don't exist until
     * Phases 4/5), so the source type itself is incidental here.
     */
    private function record(InventoryItem $item, InventoryMovementType $type, string $quantity): \App\Models\InventoryStockMovement
    {
        return $this->service()->record($item, $this->location, $type, $quantity, $this->user, InventorySourceType::Adjustment);
    }

    public function test_receiving_100_then_issuing_30_leaves_on_hand_at_70(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '100');
        $this->record($this->item, InventoryMovementType::Issue, '30');

        $stock = $this->item->stock()->where('location_id', $this->location->id)->sole();
        $this->assertSame('70.000', $stock->on_hand);

        $ledgerSum = $this->item->movements()->get()->sum(fn ($m) => $m->quantity * $m->direction);
        $this->assertSame(70.0, $ledgerSum);
    }

    public function test_every_movements_balance_after_matches_the_running_balance(): void
    {
        $receipt = $this->record($this->item, InventoryMovementType::Receipt, '50');
        $this->assertSame('50.000', $receipt->balance_after);

        $issue = $this->record($this->item, InventoryMovementType::Issue, '20');
        $this->assertSame('30.000', $issue->balance_after);

        $receipt2 = $this->record($this->item, InventoryMovementType::Receipt, '5');
        $this->assertSame('35.000', $receipt2->balance_after);
    }

    public function test_decimal_quantities_survive_without_precision_loss(): void
    {
        $uom = InventoryUnitOfMeasure::create(['code' => 'M', 'name' => 'Metre', 'decimal_places' => 2]);
        $cable = InventoryItem::create([
            'code' => 'ELEC-0001', 'name' => 'Cable', 'category_id' => $this->item->category_id, 'uom_id' => $uom->id,
        ]);
        $cable->stock()->create(['location_id' => $this->location->id, 'on_hand' => 0, 'reserved' => 0]);

        $this->record($cable, InventoryMovementType::Receipt, '100.50');
        $this->record($cable, InventoryMovementType::Issue, '30.25');

        $this->assertSame('70.250', $cable->stock()->sole()->on_hand);
    }

    public function test_a_zero_or_negative_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->record($this->item, InventoryMovementType::Receipt, '0');
    }

    public function test_on_hand_cannot_go_negative_by_default(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->record($this->item, InventoryMovementType::Issue, '10');
    }

    public function test_on_hand_can_go_negative_when_the_setting_allows_it(): void
    {
        InventorySetting::create([
            'key' => 'allow_negative_stock', 'value' => 'true', 'data_type' => 'boolean', 'category' => 'stock',
        ]);

        $movement = $this->record($this->item, InventoryMovementType::Issue, '10');

        $this->assertSame('-10.000', $movement->balance_after);
    }

    public function test_recording_against_an_item_with_no_stock_row_fails_loudly(): void
    {
        $orphanUom = InventoryUnitOfMeasure::create(['code' => 'BOX', 'name' => 'Box', 'decimal_places' => 0]);
        $orphanItem = InventoryItem::create([
            'code' => 'STAT-0002', 'name' => 'No stock row', 'category_id' => $this->item->category_id, 'uom_id' => $orphanUom->id,
        ]);

        $this->expectException(RuntimeException::class);

        $this->record($orphanItem, InventoryMovementType::Receipt, '10');
    }

    public function test_reversing_a_movement_restores_on_hand_exactly(): void
    {
        $receipt = $this->record($this->item, InventoryMovementType::Receipt, '40');
        $this->service()->reverse($receipt, $this->user, 'Entered in error');

        $stock = $this->item->stock()->sole();
        $this->assertSame('0.000', $stock->on_hand);
        $this->assertTrue($receipt->fresh()->is_reversed);
    }

    public function test_a_reversal_that_would_push_on_hand_negative_is_blocked(): void
    {
        $receipt = $this->record($this->item, InventoryMovementType::Receipt, '40');
        $this->record($this->item, InventoryMovementType::Issue, '40');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reverse($receipt, $this->user, 'Would go negative');
    }

    public function test_a_movement_cannot_be_reversed_twice(): void
    {
        $receipt = $this->record($this->item, InventoryMovementType::Receipt, '40');
        $this->service()->reverse($receipt, $this->user, 'First reversal');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reverse($receipt->fresh(), $this->user, 'Second reversal attempt');
    }

    public function test_reconciliation_reports_no_drift_after_a_mixed_batch(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '100');
        $this->record($this->item, InventoryMovementType::Issue, '40');
        $this->record($this->item, InventoryMovementType::AdjustOut, '5');
        $receipt = $this->record($this->item, InventoryMovementType::Receipt, '10');
        $this->service()->reverse($receipt, $this->user, 'Correction');

        $this->assertCount(0, $this->service()->reconcileDrift());
    }

    public function test_reconciliation_detects_a_hand_corrupted_cached_balance(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '100');

        InventoryItemStock::query()
            ->where('item_id', $this->item->id)
            ->where('location_id', $this->location->id)
            ->update(['on_hand' => 999]);

        $drift = $this->service()->reconcileDrift();

        $this->assertCount(1, $drift);
        $this->assertSame($this->item->id, $drift->first()->item_id);
    }

    /**
     * Regression test — an INNER JOIN starting from the movements
     * table (which is what the spec's own SQL in 7.4 does) misses this
     * case entirely, since an item with zero movements has no ledger
     * rows to join against. reconcileDrift() starts from
     * inventory_item_stock instead specifically to catch this.
     */
    public function test_reconciliation_detects_a_corrupted_balance_on_an_item_with_zero_movements(): void
    {
        InventoryItemStock::query()
            ->where('item_id', $this->item->id)
            ->where('location_id', $this->location->id)
            ->update(['on_hand' => 999]);

        $drift = $this->service()->reconcileDrift();

        $this->assertCount(1, $drift);
        $this->assertSame($this->item->id, $drift->first()->item_id);
        $this->assertEquals(0, $drift->first()->ledger_balance);
    }

    public function test_reserving_holds_stock_without_touching_on_hand(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '100');

        $this->service()->reserve($this->item, $this->location, '30');

        $stock = $this->item->stock()->sole();
        $this->assertSame('100.000', $stock->on_hand);
        $this->assertSame('30.000', $stock->reserved);
        $this->assertSame('70.000', $stock->available);
    }

    public function test_reserving_more_than_available_is_blocked(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '10');

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reserve($this->item, $this->location, '11');
    }

    public function test_release_floors_reserved_at_zero(): void
    {
        $this->record($this->item, InventoryMovementType::Receipt, '10');
        $this->service()->reserve($this->item, $this->location, '4');

        $this->service()->release($this->item, $this->location, '100');

        $this->assertSame('0.000', $this->item->stock()->sole()->reserved);
    }
}
