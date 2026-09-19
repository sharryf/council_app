<?php

namespace App\Filament\Assets\Resources\EditRequests\Tables;

use App\Filament\Assets\Resources\EditRequests\AssetEditRequestResource;
use App\Models\AssetEditRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetEditRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'requestedBy', 'reviewedBy']))
            ->columns([
                TextColumn::make('asset.asset_tag')->label('Tag'),
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('fields')
                    ->label('Fields changed')
                    ->state(fn (AssetEditRequest $record): string => $record->fieldsSummary()),
                TextColumn::make('reason')->label('Reason')->limit(40),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Requested')->date()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('reviewedBy.name')->label('Approved by')->placeholder('—'),
            ])
            ->recordActions([
                AssetEditRequestResource::approveAction(),
                AssetEditRequestResource::rejectAction(),
            ])
            ->defaultSort('requested_at', 'desc');
    }
}
