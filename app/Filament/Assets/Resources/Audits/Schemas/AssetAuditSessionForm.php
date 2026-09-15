<?php

namespace App\Filament\Assets\Resources\Audits\Schemas;

use App\Enums\AssetAuditScopeType;
use App\Models\AssetBuilding;
use App\Models\AssetRoom;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssetAuditSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Session name')
                    ->placeholder('e.g. "Q3 2026 — Main Building"')
                    ->required()
                    ->maxLength(160),
                Select::make('scope_type')
                    ->label('Scope')
                    ->options(collect(AssetAuditScopeType::cases())->mapWithKeys(fn (AssetAuditScopeType $s): array => [$s->value => $s->getLabel()]))
                    ->default(AssetAuditScopeType::All->value)
                    ->live()
                    ->required(),
                Select::make('scope_building_id')
                    ->label('Building')
                    ->options(fn () => AssetBuilding::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('scope_type') === AssetAuditScopeType::Building->value)
                    ->required(fn ($get) => $get('scope_type') === AssetAuditScopeType::Building->value)
                    ->searchable(),
                Select::make('scope_room_id')
                    ->label('Room')
                    ->options(fn () => AssetRoom::query()
                        ->where('is_active', true)
                        ->with('building')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (AssetRoom $room): array => [$room->id => $room->path()]))
                    ->visible(fn ($get) => $get('scope_type') === AssetAuditScopeType::Room->value)
                    ->required(fn ($get) => $get('scope_type') === AssetAuditScopeType::Room->value)
                    ->searchable(),
            ]);
    }
}
