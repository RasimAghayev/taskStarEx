<?php

use App\Http\Controllers\HealthController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Deliberately registered outside the web/api middleware
            // groups -- the same convention Laravel itself uses for the
            // built-in /up check above. A liveness/readiness probe must
            // not pay for CSRF/session overhead and must not be subject
            // to the API's CORS/rate-limit policy (Phase 9).
            Route::get('/health/live', [HealthController::class, 'live']);
            Route::get('/health/ready', [HealthController::class, 'ready']);
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Applies the "api" named rate limiter (see AppServiceProvider)
        // to every /api/* route. Phase 9: this repo had zero rate
        // limiting before this phase.
        $middleware->throttleApi();

        // Laravel's own ApplicationBuilder registers a default guest
        // redirect callback (Authenticate::redirectUsing(fn () =>
        // route('login'))) regardless of anything configured here. This
        // app is a pure JSON API (Sanctum tokens, no web session guard --
        // the real login page is the separate Nuxt SPA, see
        // docs/adr/0004-...), so no "login" named route exists, and that
        // default callback throws RouteNotFoundException the moment an
        // unauthenticated request doesn't send "Accept: application/json"
        // -- a real 500 found via this phase's manual Docker smoke test.
        // Overriding it to "no redirect target" lets withExceptions()'s
        // AuthenticationException handler below run cleanly instead.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This app is a pure JSON API (Sanctum token auth, no web
        // session/cookie guard, see docs/adr/0004-...) -- there is no
        // Laravel-side "login" web route to redirect an unauthenticated
        // visitor to (the real login page is the separate Nuxt SPA).
        // Without this, Laravel's default Authenticate middleware tries
        // to redirect any request that doesn't send
        // "Accept: application/json" to route('login'), which doesn't
        // exist here, throwing RouteNotFoundException -> a bare 500 (and,
        // worse, an APP_DEBUG=true stack-trace page leaked to an
        // unauthenticated caller) instead of a clean 401. Every
        // unauthenticated request to this API gets the same honest JSON
        // 401 regardless of the caller's Accept header.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        });
    })->create();
