<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
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
