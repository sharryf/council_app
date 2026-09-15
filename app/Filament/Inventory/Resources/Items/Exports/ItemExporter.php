<?php

namespace App\Filament\Inventory\Resources\Items\Exports;

use App\Models\InventoryItem;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;

/**
 * The item master catalog — not opening balances (spec 8.6, which
 * needs the ledger service and belongs to a later phase).
 */
class ItemExporter extends Exporter
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('code')->label('Code'),
            ExportColumn::make('name')->label('Name'),
            ExportColumn::make('description')->label('Description'),
            ExportColumn::make('category.code')->label('Category'),
            ExportColumn::make('uom.code')->label('UoM'),
            ExportColumn::make('brand')->label('Brand'),
            ExportColumn::make('model_spec')->label('Model/Spec'),
            ExportColumn::make('bin_location')->label('Bin Location'),
            ExportColumn::make('reorder_level')->label('Reorder Level'),
            ExportColumn::make('reorder_qty')->label('Reorder Qty'),
            ExportColumn::make('max_level')->label('Max Level'),
            ExportColumn::make('lead_time_days')->label('Lead Time Days'),
            ExportColumn::make('defaultSupplier.code')->label('Default Supplier'),
            ExportColumn::make('is_stock_tracked')->label('Stock Tracked'),
            ExportColumn::make('is_active')->label('Active'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your item export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
