<?php

namespace App\Providers\Filament;

use App\Filament\Assets\Pages\Dashboard;
use App\Support\ColorPalette;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * A fourth panel alongside Admin, Bureau, and Inventory — Assets needs
 * its own role vocabulary (App\Enums\AssetRole: Admin/Manager/Viewer,
 * not ranked — Manager can approve/reject while Admin explicitly
 * cannot) that doesn't fit the generic Viewer/Editor/Approver ranking
 * the admin panel's other modules share, the same reasoning that put
 * Inventory and Bureau in their own panels. It was originally built as
 * plain Resources inside the admin panel (alongside Document Signing),
 * but that meant both modules' "Settings" nav groups rendered together
 * in one shared sidebar with no separation — moving it here gives it a
 * fully isolated sidebar, matching house convention for a module this
 * size.
 *
 * Same shared auth guard/session as Admin, Bureau, and Inventory — a
 * user already authenticated in /admin can navigate straight into
 * /assets without re-authenticating.
 */
class AssetPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('assets')
            ->path('assets')
            ->login()
            ->homeUrl(fn (): string => route('filament.admin.pages.dashboard'))
            ->colors([
                'primary' => Color::hex('#0E7A82'),
                'accent' => Color::hex('#B8934A'),
                'gray' => Color::Stone,
                'muted' => ColorPalette::tintedScale('#BFE0DE'),
            ])
            ->sidebarWidth('14rem')
            ->discoverResources(in: app_path('Filament/Assets/Resources'), for: 'App\Filament\Assets\Resources')
            ->discoverPages(in: app_path('Filament/Assets/Pages'), for: 'App\Filament\Assets\Pages')
            ->pages([
                Dashboard::class,
            ])
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
