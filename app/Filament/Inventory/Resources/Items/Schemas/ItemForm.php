<?php

namespace App\Filament\Inventory\Resources\Items\Schemas;

use App\Models\InventoryItem;
use App\Models\InventoryItemCategory;
use App\Models\InventorySupplier;
use App\Models\InventoryUnitOfMeasure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('Leave blank to auto-generate from the category (e.g. STAT-0001).')
                    ->maxLength(30)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state)
                    // BR-01: immutable once any movement exists — no
                    // movements are ever written before Phase 3, so this
                    // is a no-op guard today that's already correct once
                    // the ledger service lands.
                    ->disabled(fn (?InventoryItem $record): bool => (bool) $record?->movements()->exists())
                    ->dehydrated(),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(150),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2),
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn () => InventoryItemCategory::query()->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Select::make('uom_id')
                    ->label('Unit of measure')
                    ->options(fn () => InventoryUnitOfMeasure::query()->where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    // BR-02: immutable once any movement exists — see
                    // the code field's own comment above.
                    ->disabled(fn (?InventoryItem $record): bool => (bool) $record?->movements()->exists())
                    ->dehydrated(),
                TextInput::make('brand')
                    ->label('Brand')
                    ->maxLength(100),
                TextInput::make('model_spec')
                    ->label('Model / Spec')
                    ->maxLength(150),
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('local')
                    ->directory('inventory/items')
                    ->visibility('private'),

                TextInput::make('reorder_level')
                    ->label('Reorder level')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText('The available quantity at or below which this item should be reordered. 0 means not tracked for reorder.'),
                TextInput::make('reorder_qty')
                    ->label('Reorder quantity')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText('Suggested order quantity.'),
                TextInput::make('max_level')
                    ->label('Max level')
                    ->numeric()
                    ->minValue(0)
                    // BR-06: max_level, if set, must exceed reorder_level.
                    ->gt('reorder_level')
                    ->helperText('Optional ceiling — must be greater than the reorder level if set.'),
                TextInput::make('lead_time_days')
                    ->label('Lead time (days)')
                    ->numeric()
                    ->integer()
                    ->minValue(0),
                Select::make('default_supplier_id')
                    ->label('Default supplier')
                    ->options(fn () => InventorySupplier::query()->where('is_active', true)->pluck('name', 'id'))
                    ->searchable(),

                Toggle::make('is_stock_tracked')
                    ->label('Stock tracked')
                    ->default(true),
                // BR-03: an item with on_hand != 0 cannot be
                // deactivated — enforced in EditItem::beforeSave()
                // rather than here, since blocking it live would need a
                // reactive round-trip just to read the current toggle
                // state. Always satisfiable today since Phase 3 hasn't
                // written any stock yet.
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }
}
