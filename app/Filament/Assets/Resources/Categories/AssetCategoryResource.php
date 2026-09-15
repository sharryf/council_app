<?php

namespace App\Filament\Assets\Resources\Categories;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Categories\Pages\CreateAssetCategory;
use App\Filament\Assets\Resources\Categories\Pages\EditAssetCategory;
use App\Filament\Assets\Resources\Categories\Pages\ListAssetCategories;
use App\Filament\Assets\Resources\Categories\Schemas\AssetCategoryForm;
use App\Filament\Assets\Resources\Categories\Tables\AssetCategoriesTable;
use App\Models\AssetCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssetCategoryResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetCategory::class;

    // Deliberately NOT nested under "assets/..." — that collides with
    // AssetResource's own "assets/{record}" route (e.g. "assets/categories"
    // would be swallowed by the {record} binding and 404 on lookup,
    // since Filament registers that route ahead of sibling resources).
    protected static ?string $slug = 'asset-categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'category';

    protected static ?string $pluralModelLabel = 'categories';

    protected static ?int $navigationSort = 1;

    /**
     * A user holding none of the three AssetRoles sees nothing here.
     */
    public static function canAccess(): bool
    {
        return self::userHasAnyAssetRole();
    }

    /**
     * Category/location setup is Admin-only (spec section 6.9) — Manager
     * and Viewer have no reason to manage reference data.
     */
    public static function canCreate(): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && ! static::isReferenced($record);
    }

    /**
     * Blocks archiving (and deleting) a category any non-deleted asset
     * uses, directly or via one of its sub-categories (implementation
     * plan section 3.1/8.6) — the category management screen is the
     * only place this needs checking, so it lives here rather than a
     * shared trait.
     */
    public static function isReferenced(AssetCategory $category): bool
    {
        $categoryIds = $category->parent_id === null
            ? [$category->id, ...$category->children()->pluck('id')]
            : [$category->id];

        return AssetCategory::query()->whereKey($categoryIds)->withCount('assets')->get()->sum('assets_count') > 0;
    }

    public static function form(Schema $schema): Schema
    {
        return AssetCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetCategories::route('/'),
            'create' => CreateAssetCategory::route('/create'),
            'edit' => EditAssetCategory::route('/{record}/edit'),
        ];
    }
}
