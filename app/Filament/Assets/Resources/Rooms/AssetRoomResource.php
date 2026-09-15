<?php

namespace App\Filament\Assets\Resources\Rooms;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\Rooms\Pages\CreateAssetRoom;
use App\Filament\Assets\Resources\Rooms\Pages\EditAssetRoom;
use App\Filament\Assets\Resources\Rooms\Pages\ListAssetRooms;
use App\Filament\Assets\Resources\Rooms\Schemas\AssetRoomForm;
use App\Filament\Assets\Resources\Rooms\Tables\AssetRoomsTable;
use App\Models\AssetRoom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssetRoomResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetRoom::class;

    // Not nested under "assets/..." — see AssetCategoryResource's slug
    // comment on the route collision with AssetResource's {record}.
    protected static ?string $slug = 'asset-rooms';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Rooms';

    protected static ?string $modelLabel = 'room';

    protected static ?string $pluralModelLabel = 'rooms';

    protected static ?int $navigationSort = 3;

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
        /** @var AssetRoom $record */
        return self::userIsAssetAdmin() && $record->assets()->doesntExist();
    }

    public static function form(Schema $schema): Schema
    {
        return AssetRoomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetRoomsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetRooms::route('/'),
            'create' => CreateAssetRoom::route('/create'),
            'edit' => EditAssetRoom::route('/{record}/edit'),
        ];
    }
}
