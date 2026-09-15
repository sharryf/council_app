<?php

namespace App\Filament\Inventory\Resources\Items\Imports;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Upserts the item master catalog by code — not opening balances (see
 * ItemExporter's own comment). category/uom/supplier columns are
 * mapped by their CODE (not raw IDs), via Filament's built-in
 * relationship-column resolution, since a human-edited CSV always uses
 * codes.
 */
class ItemImporter extends Importer
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Code')
                ->requiredMapping()
                ->rules(['required', 'max:30']),
            ImportColumn::make('name')
                ->label('Name')
                ->requiredMapping()
                ->rules(['required', 'max:150']),
            ImportColumn::make('description')
                ->label('Description'),
            ImportColumn::make('category')
                ->label('Category')
                ->relationship(resolveUsing: 'code')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('uom')
                ->label('UoM')
                ->relationship(resolveUsing: 'code')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('brand')
                ->label('Brand'),
            ImportColumn::make('model_spec')
                ->label('Model/Spec'),
            ImportColumn::make('bin_location')
                ->label('Bin Location'),
            ImportColumn::make('reorder_level')
                ->label('Reorder Level')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('reorder_qty')
                ->label('Reorder Qty')
                ->numeric()
                ->rules(['numeric', 'min:0']),
            ImportColumn::make('max_level')
                ->label('Max Level')
                ->numeric(),
            ImportColumn::make('lead_time_days')
                ->label('Lead Time Days')
                ->integer(),
            ImportColumn::make('supplier')
                ->label('Default Supplier')
                ->relationship('defaultSupplier', resolveUsing: 'code'),
            ImportColumn::make('is_stock_tracked')
                ->label('Stock Tracked')
                ->boolean(),
            ImportColumn::make('is_active')
                ->label('Active')
                ->boolean(),
        ];
    }

    public function resolveRecord(): ?InventoryItem
    {
        return InventoryItem::firstOrNew(['code' => $this->data['code']]);
    }

    /**
     * Only fires for genuinely new rows (Importer::__invoke() only
     * calls the 'afterCreate' hook when the record didn't already
     * exist) — matches CreateItem::afterCreate()'s own stock
     * bootstrapping, so an imported item gets the same zero-balance
     * inventory_item_stock row per active location as one created
     * through the form.
     */
    protected function afterCreate(): void
    {
        $this->record->created_by = auth()->id();
        $this->record->saveQuietly();

        $locationIds = InventoryLocation::query()->where('is_active', true)->pluck('id');

        foreach ($locationIds as $locationId) {
            $this->record->stock()->create([
                'location_id' => $locationId,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your item import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
