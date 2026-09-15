<?php

namespace App\Filament\Auth\Responses;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Overrides Filament's default (Filament\Auth\Http\Responses\LogoutResponse
 * — bound in App\Providers\AppServiceProvider), which sends the user back
 * to whichever panel they logged out from. Bureau is a separate,
 * Dhivehi-only panel with no user-facing purpose of its own to return
 * to after signing out, so logging out from it should land on the main
 * app's login screen instead — every other panel keeps the default
 * per-panel behavior.
 */
class LogoutResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'bureau') {
            return redirect()->to(Filament::getPanel('admin')->getLoginUrl());
        }

        return redirect()->to(
            Filament::hasLogin() ? Filament::getLoginUrl() : Filament::getUrl(),
        );
    }
}
