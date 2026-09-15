<?php

namespace App\Providers;

use App\Filament\Auth\Responses\LogoutResponse;
use App\Models\InventorySetting;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * The container binding happens here (boot(), not register()) so it
     * runs after Filament's own service provider has already bound its
     * default LogoutResponse — see App\Filament\Auth\Responses\LogoutResponse
     * for why the Bureau panel needs a different one.
     */
    public function boot(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);

        // Storage (config('app.timezone')) stays UTC — every ->dateTime()
        // column/entry across the Inventory module falls back to this at
        // display time only (Filament's own CanFormatState::getTimezone()),
        // so wiring it here is the one place that needs to know about the
        // inventory_settings 'timezone' row. A Closure, not a resolved
        // string, so it re-reads the (cached) setting on every request
        // rather than freezing whatever it was at boot.
        FilamentTimezone::set(fn (): string => InventorySetting::timezone());
    }
}
