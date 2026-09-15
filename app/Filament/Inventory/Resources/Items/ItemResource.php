<?php

namespace App\Filament\Inventory\Resources\Items;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Items\Pages\CreateItem;
use App\Filament\Inventory\Resources\Items\Pages\EditItem;
use App\Filament\Inventory\Resources\Items\Pages\ListItems;
use App\Filament\Inventory\Resources\Items\Pages\ViewItem;
use App\Filament\Inventory\Resources\Items\RelationManagers\MovementsRelationManager;
use App\Filament\Inventory\Resources\Items\Schemas\ItemForm;
use App\Filament\Inventory\Resources\Items\Schemas\ItemInfolist;
use App\Filament\Inventory\Resources\Items\Tables\ItemsTable;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 2;

    /**
     * Any inventory role may browse the catalogue (spec 5.2).
     */
    public static function canAccess(): bool
    {
        return self::userHasAnyInventoryRole();
    }

    /**
     * Only Stock Admin/Admin may create/edit/deactivate items
     * (spec 5.2).
     */
    public static function canCreate(): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userIsStockAdminOrAbove();
    }

    /**
     * BR-04: an item with any movement can never be hard-deleted, only
     * deactivated. No movements are ever written before Phase 3, so
     * this always allows deletion today — the guard is here so it's
     * already correct once the ledger service lands.
     */
    public static function canDelete(Model $record): bool
    {
        /** @var InventoryItem $record */
        return self::userIsStockAdminOrAbove() && ! $record->movements()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'create' => CreateItem::route('/create'),
            'view' => ViewItem::route('/{record}'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }

    /**
     * Spec 10.3's "Stock Movements" tab, deferred here from Phase 2 —
     * see MovementsRelationManager's own comment.
     */
    public static function getRelations(): array
    {
        return [
            MovementsRelationManager::class,
        ];
    }
}
