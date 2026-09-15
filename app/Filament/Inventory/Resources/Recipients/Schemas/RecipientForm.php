<?php

namespace App\Filament\Inventory\Resources\Recipients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('identification_no')
                    ->label('Identification No')
                    ->maxLength(50),
                TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('department')
                    ->label('Department')
                    ->maxLength(150),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
