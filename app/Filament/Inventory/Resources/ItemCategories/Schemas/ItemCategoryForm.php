<?php

namespace App\Filament\Inventory\Resources\ItemCategories\Schemas;

use App\Models\InventoryItemCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemCategoryForm
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
                Select::make('parent_id')
                    ->label('Parent category')
                    ->options(fn (?InventoryItemCategory $record): array => InventoryItemCategory::query()
                        ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
