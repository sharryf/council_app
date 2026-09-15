<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\ThaanaTransliterator;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $get, callable $set): void {
                        if (blank($get('name_dv'))) {
                            $set('name_dv', filled($state) ? ThaanaTransliterator::transliterate($state) : null);
                        }
                    }),
                TextInput::make('name_dv')
                    ->label('Name (Dhivehi)')
                    ->helperText('Used on the Bureau module\'s Dhivehi pages. Pre-filled as a rough draft from the name above — please correct it.')
                    ->extraInputAttributes(['dir' => 'rtl']),
                TextInput::make('position')
                    ->placeholder('e.g. Council Officer')
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $get, callable $set): void {
                        if (blank($get('position_dv'))) {
                            $set('position_dv', filled($state) ? ThaanaTransliterator::transliterate($state) : null);
                        }
                    }),
                TextInput::make('position_dv')
                    ->label('Position (Dhivehi)')
                    ->helperText('Used on the Bureau module\'s Dhivehi pages. Pre-filled as a rough draft from the position above — please correct it.')
                    ->extraInputAttributes(['dir' => 'rtl'])
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Leave blank to keep the current password.' : ''),

                Toggle::make('is_admin')
                    ->label('Admin (system owner)')
                    ->helperText('Access to every module at Approver level, plus this Users list. Not tied to the modules below.')
                    ->columnSpanFull(),

                Section::make('Module access')
                    ->description("Which apps this user can see at all. What they can do inside an app (Viewer/Editor/Approver) is set from that app's own Roles page, not here.")
                    ->schema([
                        CheckboxList::make('module_access')
                            ->label('')
                            ->options(collect(config('modules'))->map(fn (array $module): string => $module['label'])->all())
                            ->columns(2)
                            ->default(array_keys(config('modules'))),
                    ]),
            ]);
    }
}
