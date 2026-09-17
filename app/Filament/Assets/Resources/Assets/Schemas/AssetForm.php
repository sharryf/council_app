<?php

namespace App\Filament\Assets\Resources\Assets\Schemas;

use App\Enums\AssetAcquisitionType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetBuilding;
use App\Models\AssetCategory;
use App\Models\AssetFundCode;
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
            ->columns(1)
            ->components([
                // Once an asset is Posted, EditAsset routes a submission
                // here into an AssetEditRequest instead of saving
                // directly — this reason is what the Manager reviewing
                // it sees, so it's asked for up front rather than as an
                // afterthought.
                Textarea::make('edit_reason')
                    ->label('Reason for this edit')
                    ->helperText('This asset is posted — changes need a Manager\'s approval before they apply.')
                    ->rows(2)
                    ->visible(fn (?Asset $record): bool => $record?->isPosted() ?? false)
                    ->required(fn (?Asset $record): bool => $record?->isPosted() ?? false),
                Section::make('Identification')
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
                        // A two-step cascade — Main category (a
                        // top-level AssetCategory) then Sub category
                        // (its children, the only ones that ever carry
                        // an asset class code) — rather than one flat
                        // list of ~110 pre-joined "Parent > Child"
                        // options. category_top_id isn't a real column;
                        // it only exists to filter the second select
                        // and is never dehydrated.
                        Select::make('category_top_id')
                            ->label('Main category')
                            ->options(fn () => AssetCategory::query()
                                ->whereNull('parent_id')
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->required()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, ?Asset $record): void {
                                if ($record?->category?->parent_id) {
                                    $component->state($record->category->parent_id);
                                }
                            })
                            ->afterStateUpdated(fn ($set) => $set('category_id', null)),
                        Select::make('category_id')
                            ->label('Sub category')
                            ->helperText('Its asset class code feeds the auto-generated inventory number below.')
                            ->options(fn ($get): array => AssetCategory::query()
                                ->where('parent_id', $get('category_top_id'))
                                ->where('is_active', true)
                                ->whereNotNull('asset_class_code')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (AssetCategory $category): array => [$category->id => "{$category->name} ({$category->asset_class_code})"])
                                ->all())
                            ->visible(fn ($get): bool => filled($get('category_top_id')))
                            ->required()
                            ->searchable(),
                        TextInput::make('brand')->label('Brand')->maxLength(120),
                        TextInput::make('model')->label('Model')->maxLength(120),
                        TextInput::make('serial_number')->label('Serial number')->maxLength(120),
                        Textarea::make('description')
                            ->label('Description / Notes')
                            ->rows(2),
                    ]),

                Section::make('Financial')
                    ->components([
                        DatePicker::make('purchase_date')
                            ->label('Purchase date')
                            ->helperText('Also sets the year used in the auto-generated inventory number below.'),
                        TextInput::make('purchase_price')
                            ->label('Purchase price')
                            ->numeric()
                            ->prefix('MVR'),
                        TextInput::make('vendor')->label('Vendor / Supplier')->maxLength(200),
                    ]),

                Section::make('Register Details')
                    ->components([
                        Select::make('asset_type')
                            ->label('Asset type')
                            ->options(collect(AssetAcquisitionType::cases())->mapWithKeys(fn (AssetAcquisitionType $type): array => [$type->value => $type->getLabel()]))
                            ->default(AssetAcquisitionType::Purchased->value)
                            ->required()
                            ->live(),
                        Select::make('fund_code')
                            ->label('Fund code')
                            ->options(fn () => AssetFundCode::query()
                                ->where('is_active', true)
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (AssetFundCode $fundCode): array => [$fundCode->code => $fundCode->label()]))
                            ->searchable(),
                        TextInput::make('po_number')
                            ->label('PO number')
                            ->helperText('e.g. PO-1359/J-GOM/2026/0166')
                            ->maxLength(80)
                            ->visible(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value)
                            ->required(fn ($get): bool => $get('asset_type') === AssetAcquisitionType::Purchased->value),
                        TextInput::make('voucher_number')
                            ->label('Voucher number')
                            ->helperText('e.g. PV-1359/J-GOM/2026/0083')
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
                    ->components([
                        // Same Main/Sub cascade shape as category above
                        // — building_top_id is a synthetic filter field,
                        // never dehydrated. Freely editable while the
                        // asset is a Draft, same as everything else on
                        // this form; once Posted, a room move must go
                        // through Request Transfer instead — locked
                        // (not just hidden) here so a direct edit can't
                        // bypass that approval.
                        Select::make('building_top_id')
                            ->label('Building')
                            ->helperText(fn (?Asset $record): ?string => $record?->isPosted()
                                ? 'This asset is posted — use Change Location instead of editing this directly.'
                                : null)
                            ->options(fn () => AssetBuilding::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->required()
                            ->dehydrated(false)
                            ->disabled(fn (?Asset $record): bool => $record?->isPosted() ?? false)
                            ->afterStateHydrated(function ($component, ?Asset $record): void {
                                if ($record?->room?->building_id) {
                                    $component->state($record->room->building_id);
                                }
                            })
                            ->afterStateUpdated(fn ($set) => $set('room_id', null)),
                        Select::make('room_id')
                            ->label('Room')
                            ->options(fn ($get): array => AssetRoom::query()
                                ->where('building_id', $get('building_top_id'))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->visible(fn ($get): bool => filled($get('building_top_id')))
                            ->required()
                            ->searchable()
                            ->disabled(fn (?Asset $record): bool => $record?->isPosted() ?? false)
                            ->dehydrated(fn (?Asset $record): bool => ! ($record?->isPosted() ?? false)),
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(AssetStatus::cases())->mapWithKeys(fn (AssetStatus $status): array => [$status->value => $status->getLabel()]))
                            ->default(AssetStatus::InUse->value)
                            ->required(),
                    ]),

                // Deliberately last, not first — the register fields
                // above are quick to fill in without a file ready to
                // hand; the photo can be added once one is. Same
                // Draft-applies-immediately / Posted-needs-approval
                // split as every other field here — EditAsset pulls
                // 'photo' out of $data before the usual diff and routes
                // it through Asset::replacePhoto() itself, either right
                // away (Draft) or once a Manager approves (Posted).
                FileUpload::make('photo')
                    ->label(fn (?Asset $record): string => $record ? 'Replace photo' : 'Photo')
                    ->helperText(function (?Asset $record): string {
                        if ($record === null) {
                            return 'Required. JPEG, PNG, or WebP — used to visually identify this asset in lists.';
                        }

                        return $record->isPosted()
                            ? 'Optional — leave blank to keep the current photo. Uploading a new one needs a Manager\'s approval, like any other change here.'
                            : 'Optional — leave blank to keep the current photo. JPEG, PNG, or WebP.';
                    })
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10 * 1024)
                    ->disk('local')
                    ->directory('assets/photos')
                    ->visibility('private')
                    ->required(fn (?Asset $record): bool => $record === null),
            ]);
    }
}
