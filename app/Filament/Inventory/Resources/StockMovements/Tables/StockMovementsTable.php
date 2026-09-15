<?php

namespace App\Filament\Inventory\Resources\StockMovements\Tables;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['item', 'location', 'performer'])->latest('movement_date'))
            ->columns(StockMovementColumns::make())
            ->defaultSort('movement_date', 'desc')
            ->filters([
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->options(fn () => InventoryItem::query()->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->options(fn () => InventoryLocation::query()->pluck('name', 'id')),
                SelectFilter::make('movement_type')
                    ->label('Type')
                    ->options(InventoryMovementType::class),
                Filter::make('movement_date')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('movement_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('movement_date', '<=', $date))),
            ])
            ->emptyStateHeading('No movements found')
            ->emptyStateDescription('Nothing has moved in or out of stock yet for these filters.')
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }
}
