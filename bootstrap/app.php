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
        // Rate limiting для API (60 запросов в минуту)
        $middleware->throttleApi();
        
        // Канонизация URL применяется глобально ко всем HTTP-запросам
        // Это гарантирует редирект /About → /about ДО роутинга, даже если путь не матчится ни одним роутом
        // Внутри middleware есть фильтр для системных путей (admin, api, auth, ...)
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Обработка ValidationException в формате RFC 7807
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'type' => 'https://stupidcms.dev/problems/validation-error',
                    'title' => 'Validation Failed',
                    'status' => 422,
                    'detail' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
