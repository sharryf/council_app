<?php

namespace App\Filament\Inventory\Pages;

use App\Filament\Inventory\Concerns\HasInventoryRoleAccess;
use App\Filament\Inventory\Widgets\InventoryRolesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Spec 10.9's "Settings → User Roles" — admin-only. Same "Page with one
 * full-width footer TableWidget" shape as App\Filament\Bureau\Pages\
 * BureauRoles, the closest existing precedent for a multi-role,
 * non-ranked (User/Approver/StockAdmin/Admin, any combination)
 * assignment screen.
 */
class InventoryRoles extends Page
{
    use HasInventoryRoleAccess;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'User Roles';

    protected static ?int $navigationSort = 21;

    protected Width|string|null $maxWidth = Width::Full;

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
        return 'User Roles';
    }

    /**
     * @return array<class-string<Widget>>
     */
    protected function getFooterWidgets(): array
    {
        return [
            InventoryRolesTable::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
