<?php

use App\Support\Errors\ErrorKernel;
use App\Support\Errors\ErrorResponseFactory;
use App\Support\Errors\HttpErrorException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Роуты теперь загружаются через RouteServiceProvider
        // для обеспечения детерминированного порядка: core → plugins → content → fallback
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\CleanupExpiredRefreshTokens::class,
        App\Console\Commands\GenerateJwtKeys::class,
        App\Console\Commands\UserMakeAdminCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Encrypt cookies (except JWT tokens and CSRF token)
        // Note: These values must match config/jwt.php and config/security.php
        $middleware->encryptCookies(except: [
            'cms_at',   // JWT access token cookie (from config/jwt.php)
            'cms_rt',   // JWT refresh token cookie (from config/jwt.php)
            'cms_csrf', // CSRF token cookie (from config/security.php)
        ]);
        
        // Rate limiting для API (120 запросов в минуту)
        $middleware->throttleApi();
        
        // Канонизация URL применяется глобально ко всем HTTP-запросам
        // Это гарантирует редирект /About → /about ДО роутинга, даже если путь не матчится ни одним роутом
        // Внутри middleware есть фильтр для системных путей (admin, api, auth, ...)
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);
        
        // Middleware order for API group: CORS → CSRF → Vary → Auth
        // CORS must be first to handle preflight and set headers
        // CSRF must be after CORS but before auth (headers/cookies must be available)
        // AddCacheVary after CSRF (for proper cache headers)
        // Verify CSRF token for state-changing API requests (after CORS, before auth)
        $middleware->appendToGroup('api', \App\Http\Middleware\VerifyApiCsrf::class);
        
        // Add Vary headers for API responses with cookies (after CORS and CSRF)
        $middleware->appendToGroup('api', \App\Http\Middleware\AddCacheVary::class);
        
        // Register custom middleware aliases
        $middleware->alias([
            'jwt.auth' => \App\Http\Middleware\JwtAuth::class,
            'jwt.auth.optional' => \App\Http\Middleware\OptionalJwtAuth::class,
            'no-cache-auth' => \App\Http\Middleware\NoCacheAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($e instanceof HttpErrorException) {
                $response = ErrorResponseFactory::make($e->payload());

                return $e->apply($response);
            }

            /** @var ErrorKernel $kernel */
            $kernel = app(ErrorKernel::class);
            $payload = $kernel->resolve($e);

            return ErrorResponseFactory::make($payload);
        });
    })->create();
