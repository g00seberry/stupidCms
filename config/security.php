<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CSRF Protection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CSRF (Cross-Site Request Forgery) protection using
    | double-submit cookie pattern.
    |
    */

    'csrf' => [
        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Name
        |--------------------------------------------------------------------------
        |
        | The name of the cookie used to store the CSRF token.
        | This cookie is NOT HttpOnly to allow JavaScript access.
        |
        */
        'cookie_name' => env('CSRF_COOKIE_NAME', 'cms_csrf'),

        /*
        |--------------------------------------------------------------------------
        | CSRF Token Lifetime
        |--------------------------------------------------------------------------
        |
        | The lifetime of the CSRF token in hours.
        | Default: 12 hours
        |
        */
        'ttl_hours' => env('CSRF_TTL_HOURS', 12),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie SameSite
        |--------------------------------------------------------------------------
        |
        | SameSite attribute for CSRF cookie.
        | For cross-origin SPA, set to 'None' (requires secure=true).
        | Options: 'Strict', 'Lax', 'None'
        | 
        | IMPORTANT: For cross-origin requests, set CSRF_SAMESITE=None and
        | CSRF_SECURE=true in .env. This requires HTTPS in production.
        |
        */
        'samesite' => env('CSRF_SAMESITE', env('JWT_SAMESITE', 'Strict')),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Secure
        |--------------------------------------------------------------------------
        |
        | Whether the CSRF cookie should only be sent over HTTPS.
        | Automatically set to true if SameSite=None.
        |
        */
        'secure' => env('CSRF_SECURE', env('APP_ENV') !== 'local'),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Domain
        |--------------------------------------------------------------------------
        |
        | The domain for the CSRF cookie.
        | Defaults to SESSION_DOMAIN or null (current domain).
        |
        */
        'domain' => env('CSRF_DOMAIN', env('SESSION_DOMAIN')),

        /*
        |--------------------------------------------------------------------------
        | CSRF Cookie Path
        |--------------------------------------------------------------------------
        |
        | The path for the CSRF cookie.
        | Default: '/' (available for all paths)
        |
        */
        'path' => env('CSRF_PATH', '/'),
    ],
];

