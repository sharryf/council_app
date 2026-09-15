<?php

namespace App\Filament\Assets\Resources\Transfers\Tables;

use App\Filament\Assets\Resources\Transfers\AssetTransferRequestResource;
use App\Models\AssetTransferRequest;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetTransferRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'fromRoom.building', 'toRoom.building', 'requestedBy']))
            ->columns([
                TextColumn::make('asset.asset_tag')->label('Tag'),
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('fromRoom.name')->label('From')->formatStateUsing(fn (AssetTransferRequest $record): string => $record->fromRoom->path()),
                TextColumn::make('toRoom.name')->label('To')->formatStateUsing(fn (AssetTransferRequest $record): string => $record->toRoom->path()),
                TextColumn::make('requestedBy.name')->label('Requested by'),
                TextColumn::make('requested_at')->label('Requested')->dateTime()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('self_approved')
                    ->label('Self-approved')
                    ->icon(fn (AssetTransferRequest $record) => $record->isSelfApproved() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->color('warning')
                    ->tooltip(fn (AssetTransferRequest $record): ?string => $record->isSelfApproved() ? 'Approved by the same person who requested it' : null),
            ])
            ->recordActions([
                AssetTransferRequestResource::approveAction(),
                AssetTransferRequestResource::rejectAction(),
                AssetTransferRequestResource::cancelAction(),
            ])
            ->defaultSort('requested_at', 'desc');
    }
}
