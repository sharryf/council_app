<?php

namespace App\Http\Controllers\Inventory;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Services\Inventory\ReorderCalculationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams spec 10.8's reorder buying-list CSV, grouped by supplier —
 * a real download route rather than a Filament header Action closure,
 * since Filament's action-dispatch discards a closure's return value
 * (see ReorderAlertsWidget's own comment on why this route exists).
 */
class ReorderBuyingListController extends Controller
{
    use HasInventoryRoleAccess;

    public function show(ReorderCalculationService $service): StreamedResponse
    {
        abort_unless(self::userIsStockAdminOrAbove(), 403);

        $rows = $service->candidateItems()
            ->sortBy(fn (InventoryItem $item): string => $item->defaultSupplier?->name ?? 'zzz_no_supplier')
            ->map(function (InventoryItem $item) use ($service): array {
                $available = $service->availableFor($item);

                return [
                    $item->defaultSupplier?->name ?? 'No supplier',
                    $item->code,
                    $item->name,
                    $available,
                    (string) $item->reorder_level,
                    $service->suggestedQty($item, $available),
                    $item->uom?->code,
                ];
            });

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Supplier', 'Code', 'Name', 'Available', 'Reorder Level', 'Suggested Qty', 'UoM']);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'inventory-buying-list-'.now()->format('Y-m-d').'.csv');
    }
}
