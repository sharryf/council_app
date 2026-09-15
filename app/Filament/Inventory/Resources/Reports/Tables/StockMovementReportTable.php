<?php

namespace App\Filament\Inventory\Resources\Reports\Tables;

use App\Enums\InventoryMovementType;
use App\Filament\Inventory\Resources\StockMovements\Tables\StockMovementColumns;
use App\Models\InventoryItem;
use App\Models\InventoryStockMovement;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Spec 14's Stock Movement / Item Ledger report — reuses
 * StockMovementColumns (Phase 4) rather than redefining the same
 * columns a third time (RecentMovementsWidget already reuses it a
 * second).
 */
class StockMovementReportTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(InventoryStockMovement::query()->with(['item', 'location', 'performer'])->latest('movement_date'))
            ->columns(StockMovementColumns::make())
            ->filters([
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->options(fn () => InventoryItem::query()->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('movement_type')
                    ->label('Type')
                    ->options(InventoryMovementType::class),
                SelectFilter::make('performed_by')
                    ->label('User')
                    ->options(fn () => User::query()->pluck('name', 'id'))
                    ->searchable(),
                Filter::make('movement_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('movement_date', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('movement_date', '<=', $date))),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No movements in this range')
            ->emptyStateDescription('Try widening the date range or clearing a filter.')
            ->emptyStateIcon(Heroicon::OutlinedQueueList);
    }
}
