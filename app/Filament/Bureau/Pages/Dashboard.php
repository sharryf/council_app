<?php

namespace App\Filament\Bureau\Pages;

use App\Filament\Bureau\Widgets\NextMeetingWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Bureau's own dashboard — a placeholder for now (Phase 1 of the
 * module: prove the separate RTL/Dhivehi panel renders correctly)
 * before Agenda/Meeting/Minutes are built. Will eventually surface the
 * next upcoming meeting per the module's spec.
 *
 * Extends Filament's own Dashboard (not a plain Page), same as
 * App\Filament\Pages\Dashboard does for the admin panel — that's what
 * makes this the panel's index route (/bureau) rather than living at
 * a slug-derived sub-path. $isDiscovered is false for the same reason
 * as that one: this class also lives under the discovered Pages
 * directory, and letting both discovery and the explicit
 * BureauPanelProvider::pages() registration claim the same route would
 * collide.
 *
 * Title/heading come from lang/dv/bureau.php (see that file's comment
 * on provisional Dhivehi).
 */
class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    public function getTitle(): string
    {
        return __('bureau.module_name');
    }

    public function getHeading(): string
    {
        return __('bureau.module_name');
    }

    public static function getNavigationLabel(): string
    {
        return 'ޑޭޝްބޯޑް';
    }

    /**
     * Overridden because the default (Filament::getWidgets()) returns
     * every panel widget including auto-discovered ones — that was
     * pulling BureauRolesTable onto the main dashboard even though it's
     * only meant to appear on the Settings > Roles page (see
     * BureauRoles::getFooterWidgets()).
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    public function getWidgets(): array
    {
        return [
            NextMeetingWidget::class,
        ];
    }
}
