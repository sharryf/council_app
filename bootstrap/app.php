<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every auth-protected plain route in routes/web.php (asset/
        // inventory/bureau attachment downloads, QR/label routes, etc.)
        // sits outside any Filament panel, so the 'auth' middleware's
        // own default guest redirect (route('login')) has nothing to
        // resolve — this app has no such route, only Filament's own
        // panel-scoped login. Without this, an unauthenticated request
        // to any of those routes 500s on RouteNotFoundException instead
        // of bouncing to the login page.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
