<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Pages\Login;
use App\Filament\Bureau\Pages\Dashboard;
use App\Filament\Bureau\Resources\Meetings\Pages\ListMeetings;
use App\Filament\Bureau\Widgets\NextMeetingWidget;
use App\Http\Middleware\SetBureauLocale;
use App\Support\ColorPalette;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * A separate panel from AdminPanelProvider (not a Resource inside it) —
 * Bureau is fully Dhivehi/RTL with the menu on the right, while the
 * rest of the app (Documents, Users, etc.) stays English/LTR. Filament's
 * sidebar/topbar chrome is shared per-panel and isn't built to flip
 * mid-panel, so this is a genuinely separate panel/layout rather than a
 * per-route override inside the admin one — see the "Bureau module"
 * section of the README for the reasoning.
 *
 * `SetBureauLocale` scopes App::setLocale('dv') to just this panel's
 * requests; `lang/vendor/filament-panels/dv/layout.php` is what flips
 * `<html dir>` to rtl for that locale (see that file's own comment).
 * Same guard/session as the admin panel (no separate login system) —
 * a user already authenticated in /admin can navigate straight into
 * /bureau without re-authenticating.
 */
class BureauPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('bureau')
            ->path('bureau')
            ->login(Login::class)
            // See filament.branding.logo's own comment on why this isn't
            // a plain ->brandLogo(url) call.
            ->brandLogo(fn () => view('filament.branding.logo', ['suffix' => 'Bureau']))
            ->favicon(asset('images/oceancy-favicon-32.png'))
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn () => view('filament.branding.login-side-panel'),
            )
            // Without this, the topbar/sidebar brand link falls back to
            // this panel's own base URL (see FilamentManager::getHomeUrl())
            // — fine for the admin panel, where that base URL already is
            // the main app dashboard, but wrong here since Bureau is a
            // separate panel: the brand should return to the main app,
            // the same as Document Signing's brand does (it lives inside
            // the admin panel, so its own base URL already is /admin).
            // The module's own dashboard is still reachable via the
            // "Dashboard" nav item (Bureau\Pages\Dashboard, unaffected).
            ->homeUrl(fn (): string => route('filament.admin.pages.dashboard'))
            ->sidebarWidth('15rem')
            // Faruma (body) + Mv Galan Normal (headings) — self-hosted,
            // since neither is on Google/Bunny Fonts (the only Thaana
            // webfont either service carries is Noto Sans Thaana, used
            // here previously). Files live in public/fonts/bureau/, with
            // both @font-face rules declared in thaana.css; the same two
            // files are also embedded (base64) into the generated PDFs —
            // see resources/views/bureau/pdf/partials/fonts.blade.php —
            // so the on-screen UI and the documents match.
            ->font('Faruma', url: asset('fonts/bureau/thaana.css'), provider: LocalFontProvider::class)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <style>
                        .fi-header-heading,
                        .fi-section-header-heading,
                        .fi-modal-heading {
                            font-family: 'Mv Galan Normal', 'Faruma', ui-sans-serif, system-ui, sans-serif;
                            font-weight: 700;
                        }
                    </style>
                    HTML),
            )
            // Placed above the page's own heading/breadcrumb, not below
            // it like a header widget would render — see
            // next-meeting-countdown.blade.php's own comment for why
            // PAGE_START was needed instead of getHeaderWidgets().
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.bureau.partials.next-meeting-countdown'),
                scopes: [ListMeetings::class],
            )
            ->colors([
                'primary' => Color::hex('#0E7A82'),
                'accent' => Color::hex('#B8934A'),
                'gray' => Color::Stone,
                'muted' => ColorPalette::tintedScale('#BFE0DE'),
            ])
            ->discoverResources(in: app_path('Filament/Bureau/Resources'), for: 'App\Filament\Bureau\Resources')
            ->discoverPages(in: app_path('Filament/Bureau/Pages'), for: 'App\Filament\Bureau\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Bureau/Widgets'), for: 'App\Filament\Bureau\Widgets')
            ->widgets([
                NextMeetingWidget::class,
            ])
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
                SetBureauLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
