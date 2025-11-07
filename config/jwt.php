<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used for signing JWTs. We use RS256 (RSA with SHA-256)
    | for asymmetric cryptography.
    |
    */
    'algo' => 'RS256',

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
    | Current Key ID
    |--------------------------------------------------------------------------
    |
    | The current key id (kid) used for signing new tokens. This allows for
    | key rotation without invalidating existing tokens.
    |
    */
    'current_kid' => env('JWT_CURRENT_KID', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Key Pairs
    |--------------------------------------------------------------------------
    |
    | Map of key IDs to their corresponding RSA key pair paths.
    | Keys can be stored in files, environment variables, or secret managers.
    |
    */
    'keys' => [
        'v1' => [
            'private_path' => storage_path('keys/jwt-v1-private.pem'),
            'public_path' => storage_path('keys/jwt-v1-public.pem'),
        ],
        // Add additional key versions for rotation:
        // 'v2' => [
        //     'private_path' => storage_path('keys/jwt-v2-private.pem'),
        //     'public_path' => storage_path('keys/jwt-v2-public.pem'),
        // ],
    ],

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

