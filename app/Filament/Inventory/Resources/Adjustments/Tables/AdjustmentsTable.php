<?php

namespace App\Filament\Inventory\Resources\Adjustments\Tables;

use App\Enums\InventoryAdjustmentStatus;
use App\Enums\InventoryAdjustmentType;
use App\Filament\Inventory\Resources\Adjustments\AdjustmentResource;
use App\Models\InventoryStockAdjustment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('lines')->orderByDesc('adjustment_no'))
            ->recordUrl(fn (InventoryStockAdjustment $record): string => AdjustmentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('adjustment_no')
                    ->label('Adjustment No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adjustment_date')->label('Date')->date()->sortable(),
                TextColumn::make('adjustment_type')->label('Type')->badge(),
                TextColumn::make('lines_count')->label('Items'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->filters([
                SelectFilter::make('adjustment_type')
                    ->label('Type')
                    ->options(InventoryAdjustmentType::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InventoryAdjustmentStatus::class),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryStockAdjustment $record): bool => AdjustmentResource::canDelete($record)),
                ]),
            ])
            ->emptyStateHeading('No adjustments yet')
            ->emptyStateDescription('Record a stock take, damage, or loss to correct stock here.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->emptyStateActions([
                AdjustmentResource::createAction()
                    ->visible(fn (): bool => AdjustmentResource::canCreate()),
            ]);
    }
}
