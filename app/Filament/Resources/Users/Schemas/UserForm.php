<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AssetRole;
use App\Enums\BureauRole;
use App\Enums\DocumentSigningRole;
use App\Enums\InventoryRole;
use App\Enums\ModuleAccessLevel;
use App\Models\User;
use App\Support\ThaanaTransliterator;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
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
                    ->unique(ignoreRecord: true)
                    // Paired with the password field's own autocomplete
                    // override below — without both, a browser that has
                    // this exact email+password saved (i.e. whoever is
                    // editing their own account) silently autofills the
                    // password field the moment the page loads. That
                    // re-saves a freshly-hashed copy of the same
                    // password on the very next save — even one where
                    // nothing was touched — which AuthenticateSession
                    // then reads as "this session's password changed
                    // under it" and quietly logs the editor out with no
                    // error at all, mid-save.
                    ->autocomplete('off'),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->autocomplete('new-password')
                    // A second layer behind the autocomplete override
                    // above: even if some password manager ignores that
                    // hint and autofills this field with the account's
                    // own current password unasked, this treats it as
                    // untouched (same as leaving it blank) rather than
                    // writing a needless new hash of the identical
                    // password — see the email field's own comment for
                    // why a rewrite here, even to an unchanged password,
                    // is never harmless.
                    ->dehydrated(fn (?string $state, ?User $record): bool => filled($state)
                        && ! ($record && Hash::check($state, $record->password)))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit' ? 'Leave blank to keep the current password.' : ''),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Off blocks this person from logging in, without deleting their account — use this when someone leaves, since their approvals/requests/signatures stay permanently linked to their user record and can\'t be deleted.')
                    ->columnSpanFull(),

                Toggle::make('is_admin')
                    ->label('Admin (system owner)')
                    ->helperText('Can manage the Users list — create, edit, deactivate, and assign roles to other users. Grants nothing in any module by itself; set this user\'s own module capabilities in Module Roles below, same as anyone else.')
                    ->columnSpanFull(),

                Section::make('Module access')
                    ->description('Which apps this user can see at all. What they can do inside an app is set below, in Module Roles.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('module_access')
                            ->label('')
                            ->options(collect(config('modules'))->map(fn (array $module): string => $module['label'])->all())
                            ->columns(2)
                            ->default(array_keys(config('modules'))),
                    ]),

                Section::make('Module Roles')
                    ->description("What this user can do inside each app they can see, set here instead of that app's own Roles page — the one place to see and change everything about a user at once.")
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Document Signing')
                            ->schema([
                                Radio::make('level_document-signing')
                                    ->label('Base access')
                                    ->options(ModuleAccessLevel::class)
                                    ->default(ModuleAccessLevel::Viewer->value)
                                    ->inline(),
                                CheckboxList::make('document_signing_roles')
                                    ->label('Roles')
                                    ->options(collect(DocumentSigningRole::cases())->mapWithKeys(fn (DocumentSigningRole $role): array => [$role->value => $role->getLabel()])->all())
                                    ->columns(2),
                            ]),
                        Section::make('Bureau')
                            ->schema([
                                CheckboxList::make('bureau_roles')
                                    ->label('Roles')
                                    ->options(collect(BureauRole::cases())->mapWithKeys(fn (BureauRole $role): array => [$role->value => $role->getLabel()])->all())
                                    ->columns(2),
                            ]),
                        Section::make('Inventory')
                            ->schema([
                                CheckboxList::make('inventory_roles')
                                    ->label('Roles')
                                    ->helperText('At least one user must hold Admin — removing the last one is blocked automatically.')
                                    ->options(collect(InventoryRole::cases())->mapWithKeys(fn (InventoryRole $role): array => [$role->value => $role->getLabel()])->all())
                                    ->columns(2),
                            ]),
                        Section::make('Assets')
                            ->schema([
                                CheckboxList::make('asset_roles')
                                    ->label('Roles')
                                    ->options(collect(AssetRole::cases())->mapWithKeys(fn (AssetRole $role): array => [$role->value => $role->getLabel()])->all())
                                    ->columns(2),
                            ]),
                        Section::make('Not yet built')
                            ->description('Registries, Permits, HR & Payroll, and Events don\'t have their own resources yet — this is the base access level they\'ll use once they do.')
                            ->collapsed()
                            ->schema([
                                Radio::make('level_registries')
                                    ->label('Registries')
                                    ->options(ModuleAccessLevel::class)
                                    ->default(ModuleAccessLevel::Viewer->value)
                                    ->inline(),
                                Radio::make('level_permits')
                                    ->label('Permits')
                                    ->options(ModuleAccessLevel::class)
                                    ->default(ModuleAccessLevel::Viewer->value)
                                    ->inline(),
                                Radio::make('level_hr-payroll')
                                    ->label('HR & Payroll')
                                    ->options(ModuleAccessLevel::class)
                                    ->default(ModuleAccessLevel::Viewer->value)
                                    ->inline(),
                                Radio::make('level_events')
                                    ->label('Events & Activities')
                                    ->options(ModuleAccessLevel::class)
                                    ->default(ModuleAccessLevel::Viewer->value)
                                    ->inline(),
                            ]),
                    ]),
            ]);
    }
}
