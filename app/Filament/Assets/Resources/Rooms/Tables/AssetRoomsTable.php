<?php

namespace App\Filament\Assets\Resources\Rooms\Tables;

use App\Filament\Assets\Resources\Rooms\AssetRoomResource;
use App\Models\AssetBuilding;
use App\Models\AssetRoom;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssetRoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('building')->withCount('assets')->orderBy('name'))
            ->columns([
                TextColumn::make('building.name')->label('Building')->sortable(),
                TextColumn::make('name')->label('Room')->searchable()->sortable(),
                TextColumn::make('assets_count')->label('Assets')->numeric(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('building_id')
                    ->label('Building')
                    ->options(fn () => AssetBuilding::query()->pluck('name', 'id')),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (AssetRoom $record): bool => AssetRoomResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (AssetRoom $record): bool => AssetRoomResource::canDelete($record)),
                ]),
            ]);
    }
}
