<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Phase 9 hardening: this file did not previously exist (Phase 3
    | finding P2-9) -- Laravel's HandleCors middleware was silently
    | falling back to its own internal defaults, which allow any origin
    | (`allowed_origins: ['*']`). Authored deliberately: this app's only
    | real client is its own Nuxt SPA, served from a different port than
    | the API in dev (see root .env.example, FE_PORT/APP_PORT), so this
    | IS a genuine cross-origin setup, not a hypothetical one.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Comma-separated list, env-driven so a real deployment's actual
    // frontend origin(s) can be set without editing code. Defaults to the
    // dev Nuxt container's published port (FE_PORT in root .env.example).
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:20251'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    // false: authentication is a Bearer token in the Authorization header
    // (Sanctum personal-access tokens), not a cookie -- no credentialed/
    // cookie-based CORS mode is needed.
    'supports_credentials' => false,

];
