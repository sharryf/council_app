<?php

namespace App\Filament\Assets\Resources\Assets\Schemas;

use App\Enums\AssetAcquisitionType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetRoom;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identification')
                    ->columns(2)
                    ->components([
                        TextInput::make('asset_tag')
                            ->label('Asset tag')
                            ->helperText('Auto-generated on save.')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(200),
                        Select::make('category_id')
                            ->label('Category')
                            ->helperText('Optional — can be assigned later. Only categories with an asset class code can be assigned; it feeds the auto-generated inventory number below.')
                            ->options(fn () => AssetCategory::query()
                                ->where('is_active', true)
                                ->whereNotNull('asset_class_code')
                                ->with('parent')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (AssetCategory $category): array => [$category->id => $category->path()]))
                            ->searchable(),
                        TextInput::make('brand')->label('Brand')->maxLength(120),
                        TextInput::make('model')->label('Model')->maxLength(120),
                        TextInput::make('serial_number')->label('Serial number')->maxLength(120),
                        Textarea::make('description')
                            ->label('Description / Notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Financial')
                    ->columns(2)
                    ->components([
                        DatePicker::make('purchase_date')
                            ->label('Purchase date')
                            ->helperText('Also sets the year used in the auto-generated inventory number below.'),
                        TextInput::make('purchase_price')
                            ->label('Purchase price')
                            ->numeric()
                            ->prefix('MVR'),
                        TextInput::make('vendor')->label('Vendor / Supplier')->maxLength(200)->columnSpanFull(),
                    ]),

                Section::make('Register Details')
                    ->columns(2)
                    ->components([
                        Select::make('asset_type')
                            ->label('Asset type')
                            ->options(collect(AssetAcquisitionType::cases())->mapWithKeys(fn (AssetAcquisitionType $type): array => [$type->value => $type->getLabel()]))
                            ->default(AssetAcquisitionType::Purchased->value)
                            ->required()
                            ->live(),
                        TextInput::make('po_number')
                            ->label('PO number')
                            ->helperText('e.g. PO-1359/J-GOM/2023/0012 — the fund code segment (between the first two "/"s) is read automatically.')
                            ->maxLength(80)
                            ->visible(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value)
                            ->required(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value),
                        TextInput::make('voucher_number')
                            ->label('Voucher number')
                            ->maxLength(80)
                            ->visible(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value)
                            ->required(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value),
                        TextInput::make('donation_reference_no')
                            ->label('Donation document ref. no.')
                            ->maxLength(80)
                            ->visible(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Donated->value)
                            ->required(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Donated->value),
                    ]),

                Section::make('Location & Status')
                    ->columns(2)
                    ->components([
                        Select::make('room_id')
                            ->label('Room')
                            ->helperText('Shown as "Building > Room". To move an asset once created, use Request Transfer instead of editing this directly.')
                            ->options(fn () => AssetRoom::query()
                                ->where('is_active', true)
                                ->with('building')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (AssetRoom $room): array => [$room->id => $room->path()]))
                            ->required()
                            ->searchable()
                            // BR-5: editing an asset with a pending
                            // transfer is allowed, but room_id must go
                            // through the transfer flow — disabled once
                            // the asset exists at all, since direct
                            // edits here would bypass approval entirely.
                            ->disabled(fn (?Asset $record): bool => $record !== null)
                            ->dehydrated(fn (?Asset $record): bool => $record === null),
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(AssetStatus::cases())->mapWithKeys(fn (AssetStatus $status): array => [$status->value => $status->getLabel()]))
                            ->default(AssetStatus::InUse->value)
                            ->required(),
                    ]),

                // Deliberately last, not first — the register fields
                // above are quick to fill in without a file ready to
                // hand; the photo can be added once one is.
                FileUpload::make('photo')
                    ->label(fn (?Asset $record): string => $record ? 'Replace photo' : 'Photo')
                    ->helperText('Required. JPEG, PNG, or WebP — used to visually identify this asset in lists.')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10 * 1024)
                    ->disk('local')
                    ->directory('assets/photos')
                    ->visibility('private')
                    ->required(fn (?Asset $record): bool => $record === null)
                    ->columnSpanFull(),
            ]);
    }
}
