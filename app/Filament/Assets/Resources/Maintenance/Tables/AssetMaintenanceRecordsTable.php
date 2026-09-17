<?php

namespace App\Filament\Assets\Resources\Maintenance\Tables;

use App\Filament\Assets\Resources\Maintenance\AssetMaintenanceRecordResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetMaintenanceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset', 'recordedBy']))
            ->columns([
                TextColumn::make('asset.asset_tag')->label('Tag'),
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('description')->label('Description')->limit(40),
                TextColumn::make('maintenance_date')->label('Date')->date()->sortable(),
                TextColumn::make('cost')->label('Cost')->money('MVR')->placeholder('—'),
                TextColumn::make('recordedBy.name')->label('Logged by'),
                TextColumn::make('approval_status')->label('Approval')->badge(),
            ])
            ->recordActions([
                AssetMaintenanceRecordResource::approveAction(),
                AssetMaintenanceRecordResource::rejectAction(),
                AssetMaintenanceRecordResource::closeAction(),
            ])
            ->defaultSort('maintenance_date', 'desc');
    }
}
