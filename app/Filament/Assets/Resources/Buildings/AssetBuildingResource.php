<?php

namespace App\Filament\Assets\Resources\Buildings;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Buildings\Pages\CreateAssetBuilding;
use App\Filament\Assets\Resources\Buildings\Pages\EditAssetBuilding;
use App\Filament\Assets\Resources\Buildings\Pages\ListAssetBuildings;
use App\Filament\Assets\Resources\Buildings\Schemas\AssetBuildingForm;
use App\Filament\Assets\Resources\Buildings\Tables\AssetBuildingsTable;
use App\Models\AssetBuilding;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssetBuildingResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetBuilding::class;

    // Not nested under "assets/..." — see AssetCategoryResource's slug
    // comment on the route collision with AssetResource's {record}.
    protected static ?string $slug = 'asset-buildings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Buildings';

    protected static ?string $modelLabel = 'building';

    protected static ?string $pluralModelLabel = 'buildings';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return self::userHasAnyAssetRole();
    }

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
        /** @var AssetBuilding $record */
        return self::userIsAssetAdmin() && $record->rooms()->doesntExist();
    }

    public static function form(Schema $schema): Schema
    {
        return AssetBuildingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetBuildingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetBuildings::route('/'),
            'create' => CreateAssetBuilding::route('/create'),
            'edit' => EditAssetBuilding::route('/{record}/edit'),
        ];
    }
}
