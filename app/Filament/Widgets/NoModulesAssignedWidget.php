<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Shown on the dashboard instead of an error/blank page when the logged
 * in user has no role that grants access to any module (see
 * config/modules.php and App\Filament\Concerns\HasModuleAccess).
 */
class NoModulesAssignedWidget extends Widget
{
    protected string $view = 'filament.widgets.no-modules-assigned';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->hasAnyModuleAccess();
    }
}
