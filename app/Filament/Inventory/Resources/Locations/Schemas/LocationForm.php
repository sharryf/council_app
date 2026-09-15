<?php

namespace App\Filament\Inventory\Resources\Locations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('address')
                    ->label('Address')
                    ->maxLength(255),
                Toggle::make('is_default')
                    ->label('Default location')
                    ->helperText('Shown as the default when starting a new item\'s stock.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
