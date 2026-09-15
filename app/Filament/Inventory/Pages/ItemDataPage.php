<?php

namespace App\Filament\Inventory\Pages;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Resources\Items\Exports\ItemExporter;
use App\Filament\Inventory\Resources\Items\Imports\ItemImporter;
use App\Filament\Inventory\Resources\Items\Imports\OpeningBalanceImporter;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Moved off the Items list header (where Export/Import/Import Opening
 * Balances sat alongside "New inventory item") into its own Settings
 * page — these are bulk data-maintenance actions, not something raised
 * while browsing the catalogue day to day. Same
 * schema-level-Actions-in-a-plain-Page shape InventoryReports.php
 * already uses to render Filament\Actions\Action objects outside of a
 * table's own header (ExportAction/ImportAction are both that same base
 * class, unchanged from how ItemsTable.php configured them).
 */
class ItemDataPage extends Page
{
    use HasInventoryRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Item Data';

    public static function canAccess(): bool
    {
        return self::userIsAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Item Data';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make('Export the item catalogue to a spreadsheet, or bulk create/update items by uploading one back — matched by Code, so an existing code updates that item and a new code creates one.'),
            Actions::make([
                ExportAction::make()
                    ->label('Export Items')
                    ->exporter(ItemExporter::class),
                ImportAction::make()
                    ->label('Import Items')
                    ->importer(ItemImporter::class),
            ]),
            Text::make('One-time setup only: load a starting on-hand quantity for items that don\'t have one yet. Each item can only receive an opening balance once — after that, use an Adjustment instead.'),
            Actions::make([
                ImportAction::make('importOpeningBalances')
                    ->label('Import Opening Balances')
                    ->modalHeading('Import Opening Balances')
                    ->importer(OpeningBalanceImporter::class),
            ]),
        ]);
    }
}
