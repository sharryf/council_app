<?php

namespace App\Filament\Inventory\Resources\Suppliers;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Inventory\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Inventory\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Inventory\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Inventory\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\InventorySupplier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SupplierResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventorySupplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Suppliers';

    /**
     * Stock Admin or above — Suppliers/Recipients are the two reference
     * lists a Stock Admin actively picks from while receiving/issuing
     * stock, so (unlike Categories/Locations/UoM) they stay visible at
     * that tier rather than being Admin-only.
     */
    public static function canAccess(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    /**
     * A supplier with any goods receipts against it can't be deleted —
     * goods_receipts.supplier_id has no cascade/null-on-delete, so this
     * would otherwise surface as a raw foreign-key error rather than a
     * message naming the actual reason. Deactivating (is_active = false)
     * is the correct way to retire a supplier that's actually been used.
     */
    public static function canDelete(Model $record): bool
    {
        /** @var InventorySupplier $record */
        return self::userIsStockAdminOrAbove() && ! $record->goodsReceipts()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
