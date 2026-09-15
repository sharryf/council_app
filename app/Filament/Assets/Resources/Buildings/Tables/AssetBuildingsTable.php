<?php

namespace App\Filament\Assets\Resources\Buildings\Tables;

use App\Filament\Assets\Resources\Buildings\AssetBuildingResource;
use App\Models\AssetBuilding;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetBuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('rooms')->orderBy('name'))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('rooms_count')->label('Rooms')->numeric(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (AssetBuilding $record): bool => AssetBuildingResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (AssetBuilding $record): bool => AssetBuildingResource::canDelete($record)),
                ]),
            ]);
    }
}
