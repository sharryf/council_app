<?php

namespace App\Filament\Inventory\Resources\UnitsOfMeasure;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\UnitsOfMeasure\Pages\CreateUnitOfMeasure;
use App\Filament\Inventory\Resources\UnitsOfMeasure\Pages\EditUnitOfMeasure;
use App\Filament\Inventory\Resources\UnitsOfMeasure\Pages\ListUnitsOfMeasure;
use App\Filament\Inventory\Resources\UnitsOfMeasure\Schemas\UnitOfMeasureForm;
use App\Filament\Inventory\Resources\UnitsOfMeasure\Tables\UnitsOfMeasureTable;
use App\Models\InventoryUnitOfMeasure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitOfMeasureResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryUnitOfMeasure::class;

    // Without this, Filament nests the resource's own auto-derived
    // slug ("unit-of-measures", from the model name) under the
    // directory-derived cluster prefix ("units-of-measure", from this
    // namespace), producing a doubled
    // /inventory/units-of-measure/unit-of-measures URL.
    protected static ?string $slug = 'units-of-measure';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Units of Measure';

    protected static ?string $modelLabel = 'unit of measure';

    protected static ?string $pluralModelLabel = 'units of measure';

    /**
     * Admin-only — a plain User or Stock-Admin-only viewer has no
     * reason to browse UoM setup (unlike Suppliers/Recipients, which
     * Stock Admins actively pick while receiving/issuing stock).
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
        return UnitOfMeasureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnitsOfMeasureTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitsOfMeasure::route('/'),
            'create' => CreateUnitOfMeasure::route('/create'),
            'edit' => EditUnitOfMeasure::route('/{record}/edit'),
        ];
    }
}
