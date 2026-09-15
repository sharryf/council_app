<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventoryLocation;

/**
 * The one place an InventoryItem gets created — auto-generates the
 * code when left blank (spec 6.3) and bootstraps an
 * inventory_item_stock row per active location (spec 6.3: "Create the
 * item_stock row automatically whenever an item is created"). Used by
 * both CreateItem (the Item resource's own create page) and the
 * "create new item inline" option on GRN line items (spec 10.7) — two
 * places that both need an item to be immediately usable by
 * StockMovementService::record(), which requires that stock row to
 * already exist.
 */
class ItemCreationService
{
    public function __construct(private readonly InventorySequenceService $sequences) {}

    public function create(array $attributes): InventoryItem
    {
        if (blank($attributes['code'] ?? null)) {
            $category = InventoryItemCategory::query()->findOrFail($attributes['category_id']);
            $attributes['code'] = $this->sequences->nextItemCode($category->code);
        }

        $item = InventoryItem::create($attributes);

        $locationIds = InventoryLocation::query()->where('is_active', true)->pluck('id');

        foreach ($locationIds as $locationId) {
            $item->stock()->create([
                'location_id' => $locationId,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
        }

        return $item;
    }
}
