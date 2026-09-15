<?php

namespace App\Filament\Inventory\Resources\ItemCategories;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\ItemCategories\Pages\CreateItemCategory;
use App\Filament\Inventory\Resources\ItemCategories\Pages\EditItemCategory;
use App\Filament\Inventory\Resources\ItemCategories\Pages\ListItemCategories;
use App\Filament\Inventory\Resources\ItemCategories\Schemas\ItemCategoryForm;
use App\Filament\Inventory\Resources\ItemCategories\Tables\ItemCategoriesTable;
use App\Models\InventoryItemCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemCategoryResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'category';

    protected static ?string $pluralModelLabel = 'categories';

    /**
     * Admin-only — a plain User or Stock-Admin-only viewer has no
     * reason to browse category setup (unlike Suppliers/Recipients,
     * which Stock Admins actively pick while receiving/issuing stock).
     */
    public static function canAccess(): bool
    {
        return self::userIsAdmin();
    }

    /**
     * Only Stock Admin/Admin may manage reference data (spec 5.2:
     * "Create item categories, UoMs, suppliers").
     */
    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canDelete(Model $record): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function form(Schema $schema): Schema
    {
        return ItemCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItemCategories::route('/'),
            'create' => CreateItemCategory::route('/create'),
            'edit' => EditItemCategory::route('/{record}/edit'),
        ];
    }
}
