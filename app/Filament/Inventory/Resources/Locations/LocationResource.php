<?php

namespace App\Filament\Inventory\Resources\Locations;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Locations\Pages\CreateLocation;
use App\Filament\Inventory\Resources\Locations\Pages\EditLocation;
use App\Filament\Inventory\Resources\Locations\Pages\ListLocations;
use App\Filament\Inventory\Resources\Locations\Schemas\LocationForm;
use App\Filament\Inventory\Resources\Locations\Tables\LocationsTable;
use App\Models\InventoryLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LocationResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Locations';

    /**
     * Admin-only — a plain User or Stock-Admin-only viewer has no
     * reason to browse location setup (unlike Suppliers/Recipients,
     * which Stock Admins actively pick while receiving/issuing stock).
     */
    public static function canAccess(): bool
    {
        return self::userIsAdmin();
    }

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
        return LocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
