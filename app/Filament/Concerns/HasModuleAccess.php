<?php

namespace App\Filament\Concerns;

use App\Enums\ModuleAccessLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Gates a Filament Resource to its module, per config/modules.php, and
 * wires up default Create/Edit/Delete authorization from the current
 * user's level in that module (see App\Models\User::roleFor()).
 *
 * Add `use HasModuleAccess;` to a Resource and declare:
 *
 *     protected static ?string $moduleKey = 'inventory';
 *
 * The Resource must declare $moduleKey itself — this trait deliberately
 * does not, since PHP requires a trait's static property and the using
 * class's override to share the exact same default value, which would
 * make every Resource fatal-error unless it happened to reuse whatever
 * placeholder this trait picked.
 *
 * Every authenticated user has at least Viewer in every module they can
 * access (see User::roleFor()), so canAccess() checks two things: that
 * $moduleKey resolves to a real config/modules.php entry (a Resource
 * with no $moduleKey set, or a typo'd one, is denied by default rather
 * than silently exposed), and that the user's User::canAccessModule()
 * hasn't excluded them from this module entirely — see UserResource,
 * which manages that per-user allow-list. Create/Edit/Delete default to
 * requiring Editor; anything
 * approval-shaped (a Sign/Approve/Finalize Action) isn't covered here —
 * gate that directly with `static::currentLevel()->atLeast(ModuleAccessLevel::Approver)`,
 * since "what counts as approval" is specific to each module.
 */
trait HasModuleAccess
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::$moduleKey
            && config('modules.'.static::$moduleKey)
            && $user
            && $user->canAccessModule(static::$moduleKey);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::currentLevel()->atLeast(ModuleAccessLevel::Editor);
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentLevel()->atLeast(ModuleAccessLevel::Editor);
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentLevel()->atLeast(ModuleAccessLevel::Editor);
    }

    protected static function currentLevel(): ModuleAccessLevel
    {
        $user = auth()->user();

        if (! $user || ! static::$moduleKey) {
            return ModuleAccessLevel::Viewer;
        }

        return $user->roleFor(static::$moduleKey);
    }
}
