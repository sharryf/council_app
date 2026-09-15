<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EditProfile;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningCleanup;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningOrganization;
use App\Filament\Resources\DocumentSigning\Documents\Pages\DocumentSigningRoles;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ModuleCardsWidget;
use App\Filament\Widgets\NoModulesAssignedWidget;
use App\Support\ColorPalette;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->sidebarWidth('15rem') // default is 20rem — narrower to leave more room for content
            ->colors([
                // "Lagoon" brand palette. `primary` and `gray` are
                // Filament's own interactive/chrome colors; `accent` and
                // `muted` are custom slots (Filament allows arbitrary
                // color names) for deliberate use in badges/icons — see
                // App\Filament\Resources\Users\Tables\UsersTable and
                // resources/views/filament/widgets/module-cards.blade.php.
                'primary' => Color::hex('#0E7A82'), // deep turquoise
                'accent' => Color::hex('#B8934A'), // weathered gold
                // Stone is one of Filament's built-in Tailwind-based
                // ramps — a warm neutral that's already tuned for both
                // themes, so `gray` no longer needs the custom
                // hue-preserving builder below.
                'gray' => Color::Stone,
                // `muted`'s source hex is pale enough that Color::hex()
                // would snap it to zero chroma at every shade (see
                // App\Support\ColorPalette), erasing the tint even at
                // shade 50 — the lightest badge background — so it uses
                // the hue-preserving ramp builder instead.
                'muted' => ColorPalette::tintedScale('#BFE0DE'), // pale lagoon
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                // Document Signing's Settings pages — see the README's
                // "Adding a new module later" section for the pattern.
                DocumentSigningRoles::class,
                DocumentSigningOrganization::class,
                DocumentSigningCleanup::class,
            ])
            ->profile(EditProfile::class, isSimple: false)
            // The topbar avatar menu carries per-user identity (profile,
            // sign out) plus, for admins only, the Users list — see
            // UserResource::shouldRegisterNavigation(), which keeps it
            // out of the main sidebar entirely.
            ->userMenuItems([
                Action::make('users')
                    ->label('Users')
                    ->icon(Heroicon::OutlinedUsers)
                    ->url(fn (): string => UserResource::getUrl())
                    ->visible(fn (): bool => auth()->user()?->hasRole('admin') ?? false),
            ])
            // Name + position header at the top of the user menu — fires
            // right before the "Profile" item regardless of whether
            // Filament renders that item as its own special header
            // (only happens when it has no url) or a normal link (our
            // case, since it links to EditProfile), so this coexists
            // with that link rather than replacing it.
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn (): View => view('filament.partials.user-menu-header', [
                    'user' => auth()->user(),
                ]),
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                ModuleCardsWidget::class,
                NoModulesAssignedWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
