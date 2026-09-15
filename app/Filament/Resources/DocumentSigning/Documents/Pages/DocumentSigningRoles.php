<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Enums\DocumentSigningRole;
use App\Filament\Resources\DocumentSigning\Documents\Widgets\DocumentSigningRolesTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use UnitEnum;

/**
 * Admin-only (see App\Enums\DocumentSigningRole) — assigns this
 * module's own Admin/Editor/Signee/Viewer roles, not the generic
 * Viewer/Editor/Approver ranking other modules use (compare
 * App\Filament\Concerns\HasModuleRolesPage, which this page
 * deliberately does not use).
 */
class DocumentSigningRoles extends Page
{
    protected static ?string $moduleKey = 'document-signing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Roles';

    // The table has 4 columns (name, position, email, roles badges) that
    // look cramped and left-hugged under Filament's default constrained
    // page width — stretch the page so the table fills the frame.
    protected Width|string|null $maxWidth = Width::Full;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasDocumentSigningRole(DocumentSigningRole::Admin);
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
            DocumentSigningRolesTable::class,
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
