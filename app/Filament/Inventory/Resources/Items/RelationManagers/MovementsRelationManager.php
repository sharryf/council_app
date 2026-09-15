<?php

namespace App\Filament\Inventory\Resources\Items\RelationManagers;

use App\Enums\InventoryMovementType;
use App\Enums\InventorySourceType;
use App\Filament\Inventory\Resources\StockMovements\Tables\StockMovementColumns;
use App\Models\InventoryStockMovement;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Spec 10.3's "Stock Movements" tab on the item view page — read-only,
 * same columns as the global ledger (StockMovementResource), just
 * pre-scoped to this item via the movements() relation (see
 * InventoryItem::movements()). Movements are only ever written by
 * StockMovementService (Phase 3), never through this UI.
 *
 * Sets $shouldSkipAuthorization directly (rather than calling
 * skipAuthorization() from table(), which runs too late — Filament
 * checks authorization at Livewire hydrate time) since this module
 * hand-rolls access control via HasInventoryRoleAccess instead of
 * Laravel Policies, same as every other Inventory resource.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static bool $shouldSkipAuthorization = true;

    // Relation managers are lazy-loaded by default (a follow-up
    // wire:init request after the page's own load) — rendering eagerly
    // instead since this tab's content is small and always wanted the
    // moment the item page opens.
    protected static bool $isLazy = false;

    protected static ?string $title = 'Stock Movements';

    public function table(Table $table): Table
    {
        return $table
            ->columns(StockMovementColumns::make())
            ->defaultSort('movement_date', 'desc')
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('Type')
                    ->options(InventoryMovementType::class),
            ])
            ->recordActions([
                Action::make('slip')
                    ->label('')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->tooltip('Download slip')
                    ->color('gray')
                    ->url(fn (InventoryStockMovement $record): ?string => match (true) {
                        $record->source_type === InventorySourceType::Issue && $record->source_id !== null => route('inventory.issue-requests.slip', $record->source_id),
                        $record->source_type === InventorySourceType::Adjustment && $record->source_id !== null => route('inventory.adjustments.slip', $record->source_id),
                        default => null,
                    })
                    ->openUrlInNewTab()
                    ->visible(fn (InventoryStockMovement $record): bool => in_array($record->source_type, [InventorySourceType::Issue, InventorySourceType::Adjustment], true)
                        && $record->source_id !== null),
            ]);
    }
}
