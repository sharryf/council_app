<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Models\InventoryItem;
use App\Models\InventoryItemStock;
use App\Models\InventoryLocation;
use App\Models\InventorySetting;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The single path that may ever write a stock_movements row or change
 * inventory_item_stock.on_hand (spec decision D1/D4). Every future
 * feature that changes real stock — GRN posting, issue/cancel,
 * adjustments, returns — must call record() (directly or via reverse())
 * rather than touching those tables itself. "The moment there are two
 * code paths that update on_hand, your balances will eventually drift"
 * — spec section 18's own warning to whoever builds this next.
 *
 * Reservation changes (reserve()/release(), spec decision D3) are a
 * deliberately separate, simpler concern living alongside record() —
 * they never touch on_hand or write a ledger row, but share the same
 * lockForUpdate() row-locking discipline, so they live on this service
 * too rather than a second one duplicating that locking pattern.
 */
class StockMovementService
{
    public function __construct(
        private readonly InventorySequenceService $sequences,
        private readonly ReorderCalculationService $reorderCalculation,
    ) {}

    /**
     * $sourceType is required, matching the spec's own NOT NULL
     * source_type column (spec 6.4) — every movement is traceable to
     * why it happened, even an ad-hoc one (use Adjustment for those).
     */
    public function record(
        InventoryItem $item,
        InventoryLocation $location,
        InventoryMovementType $type,
        string $quantity,
        User $performer,
        InventorySourceType $sourceType,
        ?int $sourceId = null,
        ?int $sourceLineId = null,
        ?string $sourceNo = null,
        ?string $reference = null,
        ?string $remarks = null,
        ?int $reversalOfId = null,
        ?int $issueReceiptId = null,
    ): InventoryStockMovement {
        if (bccomp($quantity, '0', 3) <= 0) {
            // BR-07: all quantities are > 0 on document lines; direction
            // carries the sign, never the quantity itself.
            throw new InvalidArgumentException("Movement quantity must be greater than zero, got {$quantity}.");
        }

        // Set inside the transaction (severity needs the before/after
        // available figures only visible there), but the notification
        // itself fires after commit — a mail send has no business
        // happening inside a DB transaction that could still roll back.
        $severityCrossedInto = null;

        $movement = DB::transaction(function () use ($item, $location, $type, $quantity, $performer, $sourceType, $sourceId, $sourceLineId, $sourceNo, $reference, $remarks, $reversalOfId, $issueReceiptId, &$severityCrossedInto): InventoryStockMovement {
            /** @var InventoryItemStock $stock */
            $stock = InventoryItemStock::query()
                ->where('item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                // Every active location gets a stock row the moment an
                // item is created (Phase 2's CreateItem::afterCreate()/
                // ItemImporter::afterCreate()) — a missing row here means
                // that bootstrapping was skipped somewhere, which is a
                // bug to surface loudly, not paper over with a fresh
                // zero-balance row.
                throw new RuntimeException("No inventory_item_stock row for item #{$item->id} at location #{$location->id}.");
            }

            $signedQuantity = bcmul($quantity, (string) $type->direction(), 3);
            $newOnHand = bcadd((string) $stock->on_hand, $signedQuantity, 3);

            if (bccomp($newOnHand, '0', 3) < 0 && ! InventorySetting::getBool('allow_negative_stock')) {
                // BR-08: on_hand may not go negative unless the setting
                // allows it.
                throw new InvalidArgumentException(
                    "Insufficient stock for {$item->code}: {$stock->on_hand} on hand, {$quantity} requested."
                );
            }

            $reservedStr = (string) $stock->reserved;
            $oldAvailable = bcsub((string) $stock->on_hand, $reservedStr, 3);
            $newAvailable = bcsub($newOnHand, $reservedStr, 3);
            $reorderLevel = (string) $item->reorder_level;
            $oldSeverity = $this->reorderCalculation->severityForAvailable($oldAvailable, $reorderLevel);
            $newSeverity = $this->reorderCalculation->severityForAvailable($newAvailable, $reorderLevel);

            if (self::severityRank($newSeverity['label']) > self::severityRank($oldSeverity['label'])) {
                $severityCrossedInto = $newSeverity['label'];
            }

            $movement = InventoryStockMovement::create([
                'movement_no' => $this->sequences->next('MOV'),
                'movement_date' => now(),
                'item_id' => $item->id,
                'location_id' => $location->id,
                'movement_type' => $type,
                'direction' => $type->direction(),
                'quantity' => $quantity,
                'balance_after' => $newOnHand,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'source_no' => $sourceNo,
                'issue_receipt_id' => $issueReceiptId,
                'reversal_of_id' => $reversalOfId,
                'reference' => $reference,
                'remarks' => $remarks,
                'performed_by' => $performer->id,
            ]);

            $stock->update([
                'on_hand' => $newOnHand,
                'last_movement_at' => $movement->movement_date,
            ]);

            return $movement;
        });

        if ($severityCrossedInto !== null) {
            app(InventoryNotifier::class)->reorderSeverityWorsened($item, $severityCrossedInto);
        }

        return $movement;
    }

    /**
     * Ordering for severity-worsened comparisons only — "Not tracked"
     * (an item with no reorder_level set) never counts as a crossing.
     */
    private static function severityRank(string $label): int
    {
        return match ($label) {
            'OK' => 0,
            'Low' => 1,
            'Critical' => 2,
            'Out of stock' => 3,
            default => -1,
        };
    }

    /**
     * Writes the exact opposite movement and marks the original
     * reversed — the single correction mechanism for the whole ledger
     * (spec decision D1). Reuses record() for the actual balance
     * mutation, so it inherits the same negative-balance guard that
     * implements "block reversal if it would push on_hand negative"
     * (spec BR-24, phrased there as a GRN rule but really a property of
     * reversal itself).
     */
    public function reverse(InventoryStockMovement $movement, User $performer, string $reason): InventoryStockMovement
    {
        if ($movement->is_reversed) {
            throw new InvalidArgumentException("Movement {$movement->movement_no} has already been reversed.");
        }

        return DB::transaction(function () use ($movement, $performer, $reason): InventoryStockMovement {
            $reversal = $this->record(
                item: $movement->item,
                location: $movement->location,
                type: $movement->movement_type->reversalType(),
                quantity: (string) $movement->quantity,
                performer: $performer,
                sourceType: InventorySourceType::Reversal,
                sourceId: $movement->id,
                sourceNo: $movement->movement_no,
                remarks: $reason,
                reversalOfId: $movement->id,
            );

            $movement->update(['is_reversed' => true]);

            return $reversal;
        });
    }

    /**
     * Spec decision D3: holds stock at approval time without touching
     * on_hand or writing a ledger row. Validates against `available`
     * (on_hand - reserved), not on_hand alone — the same guard the
     * approve action needs is enforced here so there is only one place
     * that can ever over-reserve.
     */
    public function reserve(InventoryItem $item, InventoryLocation $location, string $quantity): void
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException("Reservation quantity must be greater than zero, got {$quantity}.");
        }

        DB::transaction(function () use ($item, $location, $quantity): void {
            /** @var InventoryItemStock $stock */
            $stock = InventoryItemStock::query()
                ->where('item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw new RuntimeException("No inventory_item_stock row for item #{$item->id} at location #{$location->id}.");
            }

            $available = bcsub((string) $stock->on_hand, (string) $stock->reserved, 3);

            if (bccomp($available, $quantity, 3) < 0) {
                throw new InvalidArgumentException(
                    "Insufficient available stock for {$item->code}: {$available} available, {$quantity} requested."
                );
            }

            $stock->update(['reserved' => bcadd((string) $stock->reserved, $quantity, 3)]);
        });
    }

    /**
     * The other half of reserve() — cancelling, issuing, or expiring a
     * request all release whatever quantity they no longer need to hold.
     * Floored at zero (BR-09: reserved must never go negative) rather
     * than throwing, since callers release exactly what they believe is
     * outstanding and a floor is the safe response to any drift.
     */
    public function release(InventoryItem $item, InventoryLocation $location, string $quantity): void
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new InvalidArgumentException("Release quantity must be greater than zero, got {$quantity}.");
        }

        DB::transaction(function () use ($item, $location, $quantity): void {
            /** @var InventoryItemStock $stock */
            $stock = InventoryItemStock::query()
                ->where('item_id', $item->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw new RuntimeException("No inventory_item_stock row for item #{$item->id} at location #{$location->id}.");
            }

            $newReserved = bcsub((string) $stock->reserved, $quantity, 3);
            $stock->update(['reserved' => bccomp($newReserved, '0', 3) < 0 ? '0.000' : $newReserved]);
        });
    }

    /**
     * Spec 7.4: any item/location where the ledger's own sum disagrees
     * with the cached on_hand balance. Should always return empty —
     * every row is a bug to alert an admin about, never silently
     * auto-corrected. Only on_hand is reconciled; reserved is never
     * ledger-derived (see this class's own top comment).
     *
     * Starts FROM inventory_item_stock with a LEFT JOIN to movements,
     * not the other way around (the spec's own SQL in 7.4 starts from
     * the ledger, which misses this case entirely) — a freshly
     * bootstrapped item with zero movements is supposed to sit at
     * exactly zero, and COALESCE(..., 0) is what lets this query catch
     * a hand-corrupted balance on an item that has no ledger history
     * at all, not just drift on items that do.
     *
     * @return \Illuminate\Support\Collection<int, object{item_id: int, location_id: int, ledger_balance: string, cached_balance: string}>
     */
    public function reconcileDrift(): \Illuminate\Support\Collection
    {
        return DB::table('inventory_item_stock as s')
            ->leftJoin('inventory_stock_movements as m', function ($join) {
                $join->on('m.item_id', '=', 's.item_id')->on('m.location_id', '=', 's.location_id');
            })
            ->selectRaw('s.item_id, s.location_id, COALESCE(SUM(m.quantity * m.direction), 0) as ledger_balance, s.on_hand as cached_balance')
            ->groupBy('s.item_id', 's.location_id', 's.on_hand')
            ->havingRaw('COALESCE(SUM(m.quantity * m.direction), 0) <> s.on_hand')
            ->get();
    }
}
