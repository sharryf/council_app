<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scopes the Dhivehi/RTL locale to the Bureau panel only — every other
 * panel (admin) stays on the app's default English locale. Registered
 * as Bureau-panel middleware in BureauPanelProvider, not globally.
 */
class SetBureauLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('dv');

        return $next($request);
    }
}
