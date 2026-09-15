<?php

namespace App\Filament\Concerns;

use App\Enums\ModuleAccessLevel;

/**
 * Gates a standalone Filament Page that manages Viewer/Editor/Approver
 * levels for one module (see App\Filament\Widgets\Concerns\HasModuleRolesTable
 * for the actual editable table) to Approver+ users of that module —
 * assigning access is itself a privileged action, one step above what
 * Editor unlocks.
 *
 * Add `use HasModuleRolesPage;` to a Page and declare:
 *
 *     protected static ?string $moduleKey = 'inventory';
 *
 * $moduleKey is declared by the consuming class, not this trait — see
 * App\Filament\Concerns\HasModuleAccess for why.
 */
trait HasModuleRolesPage
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && static::$moduleKey
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasModuleLevel(static::$moduleKey, ModuleAccessLevel::Approver);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
