<?php

namespace App\Filament\Assets\Pages;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Imports\AssetImporter;
use BackedEnum;
use Filament\Actions\ImportAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Same schema-level-Actions-in-a-plain-Page shape Inventory's
 * ItemDataPage uses — every imported row lands as a Draft asset (see
 * AssetImporter::saveRecord()), so it's freely editable and needs an
 * explicit Post before it counts as part of the finalized register.
 */
class AssetBulkUpload extends Page
{
    use HasAssetRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Bulk Upload';

    protected static ?int $navigationSort = 50;

    public static function canAccess(): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Bulk Upload';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Upload a CSV or Excel file to create many assets at once for an initial register migration. Every row lands as a Draft — freely editable — until someone reviews it and clicks Post.'),
            Actions::make([
                ImportAction::make()
                    ->label('Import Assets')
                    ->importer(AssetImporter::class),
            ]),
        ]);
    }
}
