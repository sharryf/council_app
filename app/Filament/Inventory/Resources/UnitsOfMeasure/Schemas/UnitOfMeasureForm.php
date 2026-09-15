<?php

namespace App\Filament\Inventory\Resources\UnitsOfMeasure\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UnitOfMeasureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(50),
                TextInput::make('decimal_places')
                    ->label('Decimal places')
                    ->helperText('0 for Piece, 2 for Metre/Litre — how many decimal places quantities of this unit allow.')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(3)
                    ->default(0)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
