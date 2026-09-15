<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventorySetting;
use App\Models\InventoryStockMovement;
use Illuminate\Support\Collection;

/**
 * Centralizes spec 7.2 (reorder detection, severity, suggested
 * quantity) and 7.3 (consumption rate / days of cover) — the same
 * "one service owns a domain calculation" shape as
 * StockMovementService/InventorySequenceService. Built for Phase 6's
 * dashboard reorder widget; ItemsTable::severityFor() also delegates
 * its bucket logic here so the two never drift.
 */
class ReorderCalculationService
{
    /**
     * Items where is_stock_tracked && is_active && reorder_level > 0 &&
     * available <= reorder_level, sorted by severity then days-of-cover
     * ascending (spec 10.8). Aggregated in PHP after eager-loading
     * stock — the same approach ItemsTable::severityFor() already
     * takes, since the catalogue is small enough that this beats a
     * raw-SQL aggregate join for a dashboard widget.
     *
     * @return Collection<int, InventoryItem>
     */
    public function candidateItems(): Collection
    {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->where('is_stock_tracked', true)
            ->where('reorder_level', '>', 0)
            ->with(['category', 'uom', 'defaultSupplier', 'stock'])
            ->get()
            ->filter(fn (InventoryItem $item): bool => bccomp($this->availableFor($item), (string) $item->reorder_level, 3) <= 0);

        return $items
            ->sortBy(function (InventoryItem $item): array {
                $available = $this->availableFor($item);
                $rank = match ($this->severityForAvailable($available, (string) $item->reorder_level)['label']) {
                    'Out of stock' => 0,
                    'Critical' => 1,
                    'Low' => 2,
                    default => 3,
                };
                $days = $this->daysOfCover($item, $available);

                return [$rank, $days === null ? PHP_INT_MAX : (float) $days];
            })
            ->values();
    }

    /**
     * on_hand - reserved, summed across every location (spec 7.1) —
     * requires $item->stock to already be eager-loaded.
     */
    public function availableFor(InventoryItem $item): string
    {
        return bcsub((string) $item->stock->sum('on_hand'), (string) $item->stock->sum('reserved'), 3);
    }

    /**
     * The batch-friendly pure version of the severity buckets —
     * ItemsTable::severityFor() delegates here (see that method's own
     * comment) rather than duplicating the thresholds.
     *
     * @return array{label: string, color: string}
     */
    public function severityForAvailable(string $available, string $reorderLevel): array
    {
        if (bccomp($reorderLevel, '0', 3) <= 0) {
            return ['label' => 'Not tracked', 'color' => 'gray'];
        }

        return match (true) {
            bccomp($available, '0', 3) <= 0 => ['label' => 'Out of stock', 'color' => 'danger'],
            bccomp($available, bcmul($reorderLevel, '0.5', 3), 3) <= 0 => ['label' => 'Critical', 'color' => 'warning'],
            bccomp($available, $reorderLevel, 3) <= 0 => ['label' => 'Low', 'color' => 'warning'],
            default => ['label' => 'OK', 'color' => 'success'],
        };
    }

    /**
     * Spec 7.2's three-tier fallback — reorder_qty if set, else fill to
     * max_level, else double the reorder level — floored at zero and
     * rounded up to a whole unit when the item's UoM has no decimal
     * places.
     */
    public function suggestedQty(InventoryItem $item, string $available): string
    {
        $qty = match (true) {
            bccomp((string) $item->reorder_qty, '0', 3) > 0 => (string) $item->reorder_qty,
            $item->max_level !== null && bccomp((string) $item->max_level, '0', 3) > 0 => bcsub((string) $item->max_level, $available, 3),
            default => bcsub(bcmul((string) $item->reorder_level, '2', 3), $available, 3),
        };

        if (bccomp($qty, '0', 3) < 0) {
            $qty = '0.000';
        }

        if (($item->uom?->decimal_places ?? 0) === 0) {
            $qty = (string) (int) ceil((float) $qty);
        }

        return $qty;
    }

    /**
     * Spec 7.3: available / average-daily-usage over
     * consumption_window_days (seeded setting, default 90) of Issue
     * movements — null (shown as "—", never infinity) when there's no
     * usage history to divide by.
     */
    public function daysOfCover(InventoryItem $item, string $available): ?string
    {
        $windowDays = (int) (InventorySetting::get('consumption_window_days') ?: 90);

        $issuedQty = (string) InventoryStockMovement::query()
            ->where('item_id', $item->id)
            ->where('movement_type', InventoryMovementType::Issue)
            ->where('movement_date', '>=', now()->subDays($windowDays))
            ->sum('quantity');

        $avgDailyUsage = bcdiv($issuedQty, (string) $windowDays, 6);

        if (bccomp($avgDailyUsage, '0', 6) <= 0) {
            return null;
        }

        return bcdiv($available, $avgDailyUsage, 1);
    }
}
