<?php

namespace App\Filament\Assets\Resources\Audits\Tables;

use App\Filament\Assets\Resources\Audits\AssetAuditSessionResource;
use App\Models\AssetAuditSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetAuditSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'items',
                'items as verified_items_count' => fn ($q) => $q->whereNotNull('verified_at'),
            ]))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->wrap()->width('280px'),
                TextColumn::make('scope_type')
                    ->label('Location')
                    ->formatStateUsing(fn (AssetAuditSession $record): string => $record->locationLabel())
                    ->wrap()
                    ->width('160px'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->state(fn (AssetAuditSession $record): string => "{$record->verified_items_count} / {$record->items_count} verified"),
                TextColumn::make('startedBy.name')->label('Started by'),
                TextColumn::make('started_at')->label('Started')->date()->sortable(),
                TextColumn::make('closed_at')->label('Closed')->date()->placeholder('—'),
            ])
            ->recordUrl(fn (AssetAuditSession $record): string => AssetAuditSessionResource::getUrl('view', ['record' => $record]))
            ->defaultSort('started_at', 'desc');
    }
}
