<?php

namespace App\Filament\Inventory\Resources\UnitsOfMeasure\Tables;

use App\Filament\Inventory\Resources\UnitsOfMeasure\UnitOfMeasureResource;
use App\Models\InventoryUnitOfMeasure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UnitsOfMeasureTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('code'))
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('decimal_places')->label('Decimal Places'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InventoryUnitOfMeasure $record): bool => UnitOfMeasureResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (InventoryUnitOfMeasure $record): bool => UnitOfMeasureResource::canDelete($record)),
                ]),
            ]);
    }
}
