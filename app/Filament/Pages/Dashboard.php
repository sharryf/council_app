<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The user's identity and sign-out are already in the topbar user menu
 * (see AdminPanelProvider::userMenuItems()), so the page doesn't need
 * its own "Dashboard" heading or a welcome/sign-out card on top of it —
 * see AdminPanelProvider, which drops Filament's default AccountWidget.
 */
class Dashboard extends BaseDashboard
{
    /**
     * Registered explicitly in AdminPanelProvider::pages() in place of
     * the vendor Dashboard, not auto-discovered — this class also lives
     * under app/Filament/Pages (the discovery directory), and letting
     * both discovery and the explicit registration claim the same `/`
     * route path would collide.
     */
    protected static bool $isDiscovered = false;

    /**
     * The "Council App" brand link in the topbar already points at this
     * same `/admin` route (see getLayout()'s note on it being how you
     * get back here from inside a module) — a "Dashboard" sidebar entry
     * pointing at the exact same URL was pure duplication.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * The home page has no sidebar — it's just the module grid, and the
     * topbar's own "Council App" link (unaffected by this) is how you
     * get back here from inside a module. Every other page keeps the
     * normal sidebar via the panel's default layout.
     */
    public function getLayout(): string
    {
        return 'filament.pages.dashboard-layout';
    }
}
