<?php

namespace App\Filament\Assets\Resources\Categories\Tables;

use App\Filament\Assets\Resources\Categories\AssetCategoryResource;
use App\Models\AssetCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent')->orderByRaw('parent_id IS NOT NULL')->orderBy('name'))
            ->columns([
                TextColumn::make('gl_code')
                    ->label('GL code')
                    ->placeholder('—'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (AssetCategory $record, string $state): string => $record->parent_id ? "— {$state}" : $state),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Top-level'),
                TextColumn::make('asset_class_code')
                    ->label('Class code')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('assets_count')
                    ->label('Assets')
                    ->counts('assets')
                    ->numeric(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (AssetCategory $record): bool => AssetCategoryResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (AssetCategory $record): bool => AssetCategoryResource::canDelete($record)),
                ]),
            ]);
    }
}
