<?php

namespace App\Filament\Assets\Resources\Rooms\Schemas;

use App\Models\AssetBuilding;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class AssetRoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('building_id')
                    ->label('Building')
                    ->options(fn () => AssetBuilding::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('name')
                    ->label('Room name')
                    ->required()
                    ->maxLength(120)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, $get) => $rule
                            ->where('building_id', $get('building_id'))
                            ->whereNull('deleted_at'),
                    ),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive rooms stay on existing assets but drop out of new create/edit dropdowns.'),
            ]);
    }
}
