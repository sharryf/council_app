<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login;
use App\Filament\Inventory\Pages\Dashboard;
use App\Support\ColorPalette;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * A third panel alongside Admin and Bureau — Inventory needs its own
 * role vocabulary (App\Enums\InventoryRole) that doesn't fit the
 * generic Viewer/Editor/Approver ranking the admin panel's other
 * modules share, the same reasoning that put Bureau in its own panel.
 * Unlike Bureau, this stays on the app's default English locale — no
 * SetBureauLocale-style middleware needed (see BureauPanelProvider's
 * own comment on why that one is scoped to just its panel).
 *
 * Same shared auth guard/session as Admin and Bureau — a user already
 * authenticated in /admin can navigate straight into /inventory without
 * re-authenticating.
 */
class InventoryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('inventory')
            ->path('inventory')
            ->login(Login::class)
            // See filament.branding.logo's own comment on why this isn't
            // a plain ->brandLogo(url) call.
            ->brandLogo(fn () => view('filament.branding.logo', ['suffix' => 'Inventory']))
            ->favicon(asset('images/oceancy-favicon-32.png'))
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn () => view('filament.branding.login-side-panel'),
            )
            ->sidebarWidth('14rem') // default is 20rem, Admin/Bureau use 15rem — narrower still, per request (13rem clipped "Units of Measure")
            ->homeUrl(fn (): string => route('filament.admin.pages.dashboard'))
            ->colors([
                'primary' => Color::hex('#0E7A82'),
                'accent' => Color::hex('#B8934A'),
                'gray' => Color::Stone,
                'muted' => ColorPalette::tintedScale('#BFE0DE'),
            ])
            ->discoverResources(in: app_path('Filament/Inventory/Resources'), for: 'App\Filament\Inventory\Resources')
            ->discoverPages(in: app_path('Filament/Inventory/Pages'), for: 'App\Filament\Inventory\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Phase 8's notification centre — the standard Laravel
            // notifications table (already migrated, User already
            // Notifiable) rather than the bespoke inventory_notifications
            // table, since this gets a working bell/unread-badge/
            // mark-all-read for free. Not lazy-loaded — same gotcha
            // MovementsRelationManager and two dashboard widgets already
            // hit (the lazy wire:init follow-up doesn't reliably fire in
            // this environment).
            ->databaseNotifications(isLazy: false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
