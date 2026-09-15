<?php

namespace App\Filament\Inventory\Resources\Recipients;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Recipients\Pages\CreateRecipient;
use App\Filament\Inventory\Resources\Recipients\Pages\EditRecipient;
use App\Filament\Inventory\Resources\Recipients\Pages\ListRecipients;
use App\Filament\Inventory\Resources\Recipients\Schemas\RecipientForm;
use App\Filament\Inventory\Resources\Recipients\Tables\RecipientsTable;
use App\Models\InventoryRecipient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RecipientResource extends Resource
{
    use HasInventoryRoleAccess;

    protected static ?string $model = InventoryRecipient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Recipients';

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
     * A recipient with any issue requests against them can't be
     * deleted — deactivating (is_active = false) is the correct way
     * to retire someone who's actually received goods, mirroring
     * SupplierResource::canDelete().
     */
    public static function canDelete(Model $record): bool
    {
        /** @var InventoryRecipient $record */
        return self::userIsStockAdminOrAbove() && ! $record->issueRequests()->exists();
    }

    public static function form(Schema $schema): Schema
    {
        return RecipientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipientsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipients::route('/'),
            'create' => CreateRecipient::route('/create'),
            'edit' => EditRecipient::route('/{record}/edit'),
        ];
    }
}
