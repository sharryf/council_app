<?php

namespace App\Filament\Inventory\Resources\Reports\Tables;

use App\Models\InventoryItem;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Spec 14's Reorder Report — a filterable, exportable standalone view
 * of the same ReorderCalculationService::candidateItems() (Phase 6)
 * the dashboard widget already renders, but as its own report rather
 * than a dashboard tile.
 */
class ReorderReportTable
{
    public static function configure(Table $table): Table
    {
        $service = app(ReorderCalculationService::class);

        return $table
            ->records(fn (): array => $service->candidateItems()->all())
            ->columns([
                TextColumn::make('code')->label('Item')->searchable()->width('110px'),
                TextColumn::make('name')->label('Name')->width('260px'),
                TextColumn::make('uom.code')->label('UoM')->width('70px'),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (InventoryItem $record) => $service->availableFor($record))
                    ->numeric()
                    ->width('100px'),
                TextColumn::make('reorder_level')->label('Reorder Level')->numeric()->width('100px'),
                TextColumn::make('shortfall')
                    ->label('Shortfall')
                    ->state(fn (InventoryItem $record) => bcsub((string) $record->reorder_level, $service->availableFor($record), 3))
                    ->numeric()
                    ->width('100px'),
                TextColumn::make('suggested_qty')
                    ->label('Suggested Qty')
                    ->state(fn (InventoryItem $record) => $service->suggestedQty($record, $service->availableFor($record)))
                    ->numeric()
                    ->width('100px'),
                TextColumn::make('days_of_cover')
                    ->label('Days of Cover')
                    ->state(function (InventoryItem $record) use ($service): string {
                        $days = $service->daysOfCover($record, $service->availableFor($record));

                        return $days === null ? '—' : (string) (int) round((float) $days);
                    })
                    ->width('100px'),
                TextColumn::make('defaultSupplier.name')->label('Supplier')->placeholder('—')->width('200px'),
                TextColumn::make('last_received_date')->label('Last Received')->date()->placeholder('—')->width('120px'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nothing to reorder')
            ->emptyStateDescription('Every tracked item is above its reorder level.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }
}
