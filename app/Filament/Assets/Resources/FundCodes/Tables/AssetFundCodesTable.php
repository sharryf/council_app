<?php

namespace App\Filament\Assets\Resources\FundCodes\Tables;

use App\Filament\Assets\Resources\FundCodes\AssetFundCodeResource;
use App\Models\AssetFundCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetFundCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Description')->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (AssetFundCode $record): bool => AssetFundCodeResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(fn (AssetFundCode $record): bool => AssetFundCodeResource::canDelete($record)),
                ]),
            ]);
    }
}
