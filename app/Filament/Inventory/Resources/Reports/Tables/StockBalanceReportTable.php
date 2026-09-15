<?php

namespace App\Filament\Inventory\Resources\Reports\Tables;

use App\Filament\Inventory\Resources\Items\Tables\ItemsTable;
use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Services\Inventory\ReorderCalculationService;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Spec 14's Stock Balance report — a current-balance view (the "as of
 * date" param spec lists would need historical reconstruction from the
 * ledger, a much bigger feature; trimmed to "now", same scope-cut
 * shape every phase since 4 has used).
 */
class StockBalanceReportTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(InventoryItem::query()->with(['uom', 'stock']))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('uom.code')->label('UoM'),
                TextColumn::make('on_hand')
                    ->label('On Hand')
                    ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('on_hand'))
                    ->numeric(),
                TextColumn::make('reserved')
                    ->label('Reserved')
                    ->state(fn (InventoryItem $record): string => (string) $record->stock->sum('reserved'))
                    ->numeric(),
                TextColumn::make('available')
                    ->label('Available')
                    ->state(fn (InventoryItem $record): string => app(ReorderCalculationService::class)->availableFor($record))
                    ->numeric(),
                TextColumn::make('reorder_level')->label('Reorder Level')->numeric(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (InventoryItem $record): string => ItemsTable::severityFor($record)['label'])
                    ->color(fn (InventoryItem $record): string => ItemsTable::severityFor($record)['color']),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => InventoryItemCategory::query()->pluck('name', 'id')),
                Filter::make('hide_zero_stock')
                    ->label('Hide zero-stock items')
                    ->default()
                    ->query(fn ($query) => $query->whereHas('stock', fn ($q) => $q->where('on_hand', '>', 0))),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No items match these filters')
            ->emptyStateDescription('Try a different category or turn off "Hide zero-stock items."')
            ->emptyStateIcon(Heroicon::OutlinedChartBar);
    }
}
