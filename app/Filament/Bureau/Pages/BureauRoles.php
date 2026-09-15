<?php

namespace App\Filament\Bureau\Pages;

use App\Enums\BureauRole;
use App\Filament\Bureau\Widgets\BureauRolesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Module-Admin-only (see App\Enums\BureauRole — "assign role in Bureau
 * Module" is specifically that role's own capability, deliberately
 * separate from Bureau Admin's meeting/minutes duties) — assigns this
 * module's six roles. Same pattern as
 * App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningRoles
 * (that module's equivalent page) — not the generic Viewer/Editor/
 * Approver ranking other modules use, since a user can hold any
 * combination of Bureau's roles at once.
 */
class BureauRoles extends Page
{
    protected static ?string $moduleKey = 'bureau';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 10;

    // The table has 4 columns (name, position, email, roles badges) that
    // look cramped and left-hugged under Filament's default constrained
    // page width — stretch the page so the table fills the frame (same
    // reasoning as DocumentSigningRoles's own $maxWidth override).
    protected Width|string|null $maxWidth = Width::Full;

    public static function getNavigationLabel(): string
    {
        return __('bureau.roles_page.nav_label');
    }

    public static function getNavigationGroup(): string
    {
        return __('bureau.settings_nav_group');
    }

    public function getTitle(): string
    {
        return __('bureau.roles_page.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasBureauRole(BureauRole::ModuleAdmin);
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
            BureauRolesTable::class,
        ];
    }

    // Filament's default footer-widget grid is 2 columns — with only
    // one widget registered, that left it filling half the (now full-width)
    // page and leaving the right half empty. One column lets it fill the row.
    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
