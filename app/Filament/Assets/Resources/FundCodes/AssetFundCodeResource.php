<?php

namespace App\Filament\Assets\Resources\FundCodes;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Resources\FundCodes\Pages\CreateAssetFundCode;
use App\Filament\Assets\Resources\FundCodes\Pages\EditAssetFundCode;
use App\Filament\Assets\Resources\FundCodes\Pages\ListAssetFundCodes;
use App\Filament\Assets\Resources\FundCodes\Schemas\AssetFundCodeForm;
use App\Filament\Assets\Resources\FundCodes\Tables\AssetFundCodesTable;
use App\Models\AssetFundCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssetFundCodeResource extends Resource
{
    use HasAssetRoleAccess;

    protected static ?string $model = AssetFundCode::class;

    protected static ?string $slug = 'asset-fund-codes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Fund Codes';

    protected static ?string $modelLabel = 'fund code';

    protected static ?string $pluralModelLabel = 'fund codes';

    protected static ?int $navigationSort = 30;

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
        return self::userIsAssetAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return AssetFundCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetFundCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssetFundCodes::route('/'),
            'create' => CreateAssetFundCode::route('/create'),
            'edit' => EditAssetFundCode::route('/{record}/edit'),
        ];
    }
}
