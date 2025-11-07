<?php

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
    ->withMiddleware(function (Middleware $middleware): void {
        // Encrypt cookies (except JWT tokens)
        $middleware->encryptCookies(except: [
            'cms_at', // JWT access token cookie
            'cms_rt', // JWT refresh token cookie
        ]);
        
        // Rate limiting для API (60 запросов в минуту)
        $middleware->throttleApi();
        
        // Канонизация URL применяется глобально ко всем HTTP-запросам
        // Это гарантирует редирект /About → /about ДО роутинга, даже если путь не матчится ни одним роутом
        // Внутри middleware есть фильтр для системных путей (admin, api, auth, ...)
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);
        
        // Add Vary headers for API responses with cookies (after CORS middleware)
        $middleware->appendToGroup('api', \App\Http\Middleware\AddCacheVary::class);
        
        // Register custom middleware aliases
        $middleware->alias([
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'no-cache-auth' => \App\Http\Middleware\NoCacheAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global RFC 7807 (Problem Details) handler for API routes
        
        // 422 Unprocessable Entity - Validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Unprocessable Entity',
                    'status' => 422,
                    'detail' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422)->header('Content-Type', 'application/problem+json');
            }
        });

        // 401 Unauthorized - Authentication errors
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Unauthorized',
                    'status' => 401,
                    'detail' => $e->getMessage() ?: 'Authentication required.',
                ], 401)->header('Content-Type', 'application/problem+json');
            }
        });

        // 403 Forbidden - Authorization errors
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Forbidden',
                    'status' => 403,
                    'detail' => 'Forbidden.',
                ], 403)->header('Content-Type', 'application/problem+json');
            }
        });

        // 404 Not Found - Route not found
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Route not found.',
                ], 404)->header('Content-Type', 'application/problem+json');
            }
        });

        // 429 Too Many Requests - Rate limit exceeded
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Too Many Requests',
                    'status' => 429,
                    'detail' => 'Rate limit exceeded.',
                ], 429)->header('Content-Type', 'application/problem+json');
            }
        });
    })->create();
