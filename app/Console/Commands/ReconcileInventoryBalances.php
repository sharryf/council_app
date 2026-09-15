<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\Inventory\StockMovementService;
use Illuminate\Console\Command;

/**
 * Spec 7.4: any item/location where the ledger's own sum disagrees
 * with the cached on_hand balance is a bug, never silently
 * auto-corrected. On-demand for now (php artisan inventory:reconcile)
 * — no scheduled-job infrastructure exists in this app yet (see the
 * Phase 1 plan's own research), so wiring this into a nightly schedule
 * is left for whichever later phase actually introduces one.
 */
class ReconcileInventoryBalances extends Command
{
    protected $signature = 'inventory:reconcile';

    protected $description = 'Report any drift between the stock movement ledger and cached on-hand balances.';

    public function handle(StockMovementService $movements): int
    {
        $drift = $movements->reconcileDrift();

        if ($drift->isEmpty()) {
            $this->info('No drift found — every cached balance matches its ledger.');

            return self::SUCCESS;
        }

        $items = InventoryItem::query()->whereIn('id', $drift->pluck('item_id'))->pluck('code', 'id');
        $locations = InventoryLocation::query()->whereIn('id', $drift->pluck('location_id'))->pluck('code', 'id');

        $this->error("Found {$drift->count()} item/location balance(s) that do not match their ledger:");

        $this->table(
            ['Item', 'Location', 'Ledger Balance', 'Cached Balance'],
            $drift->map(fn ($row) => [
                $items[$row->item_id] ?? "#{$row->item_id}",
                $locations[$row->location_id] ?? "#{$row->location_id}",
                $row->ledger_balance,
                $row->cached_balance,
            ]),
        );

        return self::FAILURE;
    }
}
