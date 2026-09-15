<?php

namespace App\Filament\Inventory\Resources\Items\Imports;

use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\Inventory\StockMovementService;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Spec 8.6: a one-time setup step, not an ongoing feature — loads the
 * starting on_hand quantity for items that already exist (see
 * ItemImporter for the item-master catalog import, a separate concern).
 * Every row becomes an OPENING movement via StockMovementService,
 * exactly like a form-driven receipt would, never a direct write to
 * inventory_item_stock.
 *
 * Overrides resolveRecord()/fillRecord()/saveRecord() rather than using
 * the base class's default model-upsert behavior — that default is
 * built for updating the resolved model's own columns from the row,
 * which is wrong here: this import's job is to write a *movement*
 * against an existing item, not to change the item record itself
 * (aside from bin_location, which is a real item field worth updating
 * while we're here).
 */
class OpeningBalanceImporter extends Importer
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('item_code')
                ->label('Item Code')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('quantity')
                ->label('Quantity')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'gt:0']),
            ImportColumn::make('bin_location')
                ->label('Bin Location'),
        ];
    }

    /**
     * Runs before validateData() (see Importer::__invoke()), so the
     * "item exists" and "not already opened" checks live here rather
     * than as column rules — a plain "not found" return would silently
     * skip the row instead of surfacing an error.
     */
    public function resolveRecord(): ?InventoryItem
    {
        $item = InventoryItem::query()->where('code', $this->data['item_code'] ?? null)->first();

        if (! $item) {
            throw new RowImportFailedException("No item found with code \"{$this->data['item_code']}\".");
        }

        // Spec 8.6: allowed only once per item; a second attempt must
        // go through a CORRECTION adjustment (Phase 8, not built yet).
        if ($item->movements()->where('movement_type', InventoryMovementType::Opening)->exists()) {
            throw new RowImportFailedException("{$item->code} already has an opening balance recorded.");
        }

        return $item;
    }

    public function fillRecord(): void
    {
        if (filled($this->data['bin_location'] ?? null)) {
            $this->record->bin_location = $this->data['bin_location'];
        }
    }

    public function saveRecord(): void
    {
        $this->record->save();

        $location = InventoryLocation::query()->where('is_default', true)->firstOrFail();

        app(StockMovementService::class)->record(
            item: $this->record,
            location: $location,
            type: InventoryMovementType::Opening,
            quantity: (string) $this->data['quantity'],
            performer: $this->import->user,
            sourceType: InventorySourceType::Opening,
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your opening balance import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
