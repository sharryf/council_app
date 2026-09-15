<?php

namespace App\Filament\Assets\Pages;

use App\Filament\Assets\Concerns\HasAssetRoleAccess;
use App\Filament\Assets\Widgets\AssetRolesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use UnitEnum;

/**
 * Admin-only (see App\Enums\AssetRole) — assigns this module's own
 * Admin/Manager/Viewer roles, not the generic Viewer/Editor/Approver
 * ranking the main admin panel's other modules use. Same shape as
 * App\Filament\Inventory\Pages\InventoryRoles — this panel now lives
 * entirely separately from the admin panel (see AssetPanelProvider),
 * so, like InventoryRoles, this can safely use the plain 'Settings'
 * group name: there's no longer a shared sidebar for it to collide
 * with Document Signing's own 'Settings' pages.
 */
class AssetRoles extends Page
{
    use HasAssetRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Roles';

    protected Width|string|null $maxWidth = Width::Full;

    public static function canAccess(): bool
    {
        return self::userIsAssetAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<class-string<Widget>>
     */
    protected function getFooterWidgets(): array
    {
        return [
            AssetRolesTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
