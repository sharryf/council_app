<?php

namespace App\Filament\Assets\Resources\FundCodes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AssetFundCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('e.g. "J-GOM".')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at')),
                TextInput::make('name')
                    ->label('Description')
                    ->helperText('Optional, e.g. "Government of Maldives".')
                    ->maxLength(160),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Inactive fund codes stay on existing assets but drop out of the create/edit dropdown.'),
            ]);
    }
}
