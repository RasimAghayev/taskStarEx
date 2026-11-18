<?php

namespace App\Providers;

use App\Models\Company\User;
use App\Observers\Company\UserAuditObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
     */
    public function boot(): void
    {
        User::observe(UserAuditObserver::class);

        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters for this app's API (Phase 9 -- there was zero
     * rate limiting anywhere before this phase).
     *
     * "api" is the general default applied to every /api/* route (see
     * bootstrap/app.php's throttleApi()). "login" and "import" are
     * deliberately tighter: the login endpoint is a brute-force target,
     * and the CSV bulk-import endpoint is a comparatively expensive,
     * abusable bulk-write surface even once authenticated.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('import', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });
    }
}
