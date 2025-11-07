<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used for signing JWTs. We use HS256 (HMAC with SHA-256)
    | for symmetric cryptography.
    |
    */
    'algo' => 'HS256',

    /*
    |--------------------------------------------------------------------------
    | Token Time-to-Live
    |--------------------------------------------------------------------------
    |
    | The lifetime of access and refresh tokens in seconds.
    | - access_ttl: 15 minutes (900 seconds)
    | - refresh_ttl: 30 days (2592000 seconds)
    |
    */
    'access_ttl' => 15 * 60,
    'refresh_ttl' => 30 * 24 * 60 * 60,

    /*
    |--------------------------------------------------------------------------
    | Clock Drift Leeway
    |--------------------------------------------------------------------------
    |
    | Time tolerance in seconds for token expiration verification.
    | Accounts for small clock differences between server and client.
    | Recommended: 2-5 seconds. Set to 0 to disable.
    |
    */
    'leeway' => env('JWT_LEEWAY', 5),

    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    |
    | The secret key used for signing JWTs with HS256 algorithm.
    | IMPORTANT: Keep this secret! Use a random 256-bit (32 bytes) string.
    | Generate with: php artisan key:generate or openssl rand -base64 32
    |
    */
    'secret' => env('JWT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Issuer & Audience
    |--------------------------------------------------------------------------
    |
    | Standard JWT claims for identifying the token issuer and intended
    | audience.
    |
    */
    'issuer' => env('JWT_ISS', 'https://stupidcms.local'),
    'audience' => env('JWT_AUD', 'stupidcms-api'),

    /*
    |--------------------------------------------------------------------------
    | Cookie Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP-only, secure cookies that store JWT tokens.
    | - access: cookie name for access tokens
    | - refresh: cookie name for refresh tokens
    | - domain: cookie domain (defaults to SESSION_DOMAIN)
    | - secure: only send over HTTPS (disabled in local environment)
    | - samesite: CSRF protection (Strict, Lax, or None)
    |   For cross-origin SPA, set JWT_SAMESITE=None (requires secure=true)
    | - path: cookie path
    |
    */
    'cookies' => [
        'access' => 'cms_at',
        'refresh' => 'cms_rt',
        'domain' => env('SESSION_DOMAIN'),
        'secure' => env('APP_ENV') !== 'local',
        'samesite' => env('JWT_SAMESITE', 'Strict'),
        'path' => '/',
    ],
];

