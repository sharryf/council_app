<?php

namespace App\Filament\Inventory\Resources\StockMovements\Tables;

use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Filament\Inventory\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Filament\Inventory\Resources\IssueRequests\IssueRequestResource;
use App\Filament\Inventory\Resources\Returns\ReturnResource;
use App\Models\InventoryGoodsReceipt;
use App\Models\InventoryIssueRequest;
use App\Models\InventoryStockMovement;
use Filament\Tables\Columns\TextColumn;

/**
 * Shared between the global Movements list (StockMovementsTable) and
 * the per-item "Stock Movements" tab (Items/RelationManagers/
 * MovementsRelationManager) — spec 10.1/10.3 are the same underlying
 * ledger view, just scoped differently.
 */
class StockMovementColumns
{
    /**
     * @return array<\Filament\Tables\Columns\Column>
     */
    public static function make(): array
    {
        return [
            TextColumn::make('movement_no')
                ->label('Movement No')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('movement_date')
                ->label('Date')
                ->dateTime()
                ->sortable(),
            TextColumn::make('movement_type')
                ->label('Type')
                ->badge(),
            TextColumn::make('in')
                ->label('In')
                ->state(fn (InventoryStockMovement $record): ?string => $record->direction === 1 ? (string) $record->quantity : null)
                ->numeric()
                ->placeholder('—'),
            TextColumn::make('out')
                ->label('Out')
                ->state(fn (InventoryStockMovement $record): ?string => $record->direction === -1 ? (string) $record->quantity : null)
                ->numeric()
                ->placeholder('—'),
            TextColumn::make('balance_after')
                ->label('Balance')
                ->numeric()
                ->weight('bold'),
            TextColumn::make('source_no')
                ->label('Doc No')
                ->searchable()
                ->placeholder('—')
                ->url(fn (InventoryStockMovement $record): ?string => match (true) {
                    $record->source_type === InventorySourceType::Grn && $record->source_id => GoodsReceiptResource::getUrl('view', ['record' => $record->source_id]),
                    $record->source_type === InventorySourceType::Issue && $record->source_id => IssueRequestResource::getUrl('view', ['record' => $record->source_id]),
                    $record->source_type === InventorySourceType::Adjustment && $record->source_id => AdjustmentResource::getUrl('view', ['record' => $record->source_id]),
                    $record->source_type === InventorySourceType::Return && $record->source_id => ReturnResource::getUrl('view', ['record' => $record->source_id]),
                    default => null,
                }),
            TextColumn::make('issued_to')
                ->label('Issued To')
                ->state(function (InventoryStockMovement $record): ?string {
                    static $cache = [];

                    if ($record->source_type !== InventorySourceType::Issue || blank($record->source_id)) {
                        return null;
                    }

                    return ($cache[$record->source_id] ??= InventoryIssueRequest::with('recipient')->find($record->source_id))
                        ?->recipient?->name;
                })
                ->placeholder('—'),
            TextColumn::make('supplier')
                ->label('Supplier')
                ->state(function (InventoryStockMovement $record): ?string {
                    static $cache = [];

                    if ($record->source_type !== InventorySourceType::Grn || blank($record->source_id)) {
                        return null;
                    }

                    return ($cache[$record->source_id] ??= InventoryGoodsReceipt::with('supplier')->find($record->source_id))
                        ?->supplier?->name;
                })
                ->placeholder('—'),
            TextColumn::make('reference')
                ->label('Reference')
                ->placeholder('—'),
            TextColumn::make('performer.name')
                ->label('Performed By'),
            TextColumn::make('remarks')
                ->label('Remarks')
                ->placeholder('—')
                ->limit(40),
        ];
    }
}
