<?php

namespace App\Filament\Assets\Resources\DeleteRequests\Tables;

use App\Filament\Assets\Resources\DeleteRequests\AssetDeleteRequestResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetDeleteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset' => fn ($q) => $q->withTrashed(), 'requestedBy', 'reviewedBy']))
            ->columns([
                TextColumn::make('asset.asset_tag')->label('Tag'),
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('reason')->label('Reason')->limit(40),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Requested')->dateTime()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('reviewedBy.name')->label('Approved by')->placeholder('—'),
            ])
            ->recordActions([
                AssetDeleteRequestResource::approveAction(),
                AssetDeleteRequestResource::rejectAction(),
            ])
            ->defaultSort('requested_at', 'desc');
    }
}
