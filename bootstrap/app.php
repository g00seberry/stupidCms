<?php

use App\Support\Http\ProblemDetailResolver;
use App\Support\Http\ProblemException;
use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use App\Support\Logging\ProblemReporter;
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
        App\Console\Commands\BackfillEntrySlugsCommand::class,
        App\Console\Commands\CleanupExpiredRefreshTokens::class,
        App\Console\Commands\GenerateAbilitiesDoc::class,
        App\Console\Commands\GenerateConfigDoc::class,
        App\Console\Commands\GenerateErdDoc::class,
        App\Console\Commands\GenerateErrorsDoc::class,
        App\Console\Commands\GenerateJwtKeys::class,
        App\Console\Commands\GenerateMediaPipelineDoc::class,
        App\Console\Commands\GenerateRoutesDoc::class,
        App\Console\Commands\GenerateSearchDoc::class,
        App\Console\Commands\OptionsGetCommand::class,
        App\Console\Commands\OptionsSetCommand::class,
        App\Console\Commands\RoutesListReservationsCommand::class,
        App\Console\Commands\RoutesReleaseCommand::class,
        App\Console\Commands\RoutesReserveCommand::class,
        App\Console\Commands\UserMakeAdminCommand::class,
        App\Domain\Plugins\Commands\PluginsSyncCommand::class,
        App\Domain\Search\Commands\SearchReindexCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Encrypt cookies (except JWT tokens and CSRF token)
        // Note: These values must match config/jwt.php and config/security.php
        $middleware->encryptCookies(except: [
            'cms_at',   // JWT access token cookie (from config/jwt.php)
            'cms_rt',   // JWT refresh token cookie (from config/jwt.php)
            'cms_csrf', // CSRF token cookie (from config/security.php)
        ]);
        
        // Rate limiting для API (60 запросов в минуту)
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
            'no-cache-auth' => \App\Http\Middleware\NoCacheAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Global RFC 7807 (Problem Details) handler for API routes
        
        $exceptions->render(function (ProblemException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $response = ProblemResponseFactory::make(
                    $e->type(),
                    detail: $e->detail(),
                    extensions: $e->extensions(),
                    headers: $e->headers(),
                    title: $e->title(),
                    status: $e->status(),
                    code: $e->code(),
                );

                return $e->apply($response);
            }
        });

        // 422 Unprocessable Entity - Validation errors
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->filter()->first();

                return ProblemResponseFactory::make(
                    ProblemType::VALIDATION_ERROR,
                    detail: $firstError ?? ProblemType::VALIDATION_ERROR->defaultDetail(),
                    extensions: ['errors' => $errors]
                );
            }
        });

        // 401 Unauthorized - Authentication errors
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ProblemResponseFactory::make(
                    ProblemType::UNAUTHORIZED,
                    detail: $e->getMessage() ?: ProblemType::UNAUTHORIZED->defaultDetail()
                );
            }
        });

        // 403 Forbidden - Authorization errors (AuthorizationException)
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ProblemResponseFactory::make(
                    ProblemType::FORBIDDEN,
                    detail: $e->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail()
                );
            }
        });

        // 403 Forbidden - Authorization errors (AccessDeniedHttpException)
        // Laravel converts AuthorizationException to AccessDeniedHttpException in some cases
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ProblemResponseFactory::make(
                    ProblemType::FORBIDDEN,
                    detail: $e->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail()
                );
            }
        });

        // 404 Not Found - Route not found
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ProblemResponseFactory::make(
                    ProblemType::NOT_FOUND,
                    detail: 'The requested resource was not found.'
                );
            }
        });

        // 429 Too Many Requests - Rate limit exceeded
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return ProblemResponseFactory::make(
                    ProblemType::RATE_LIMIT_EXCEEDED,
                    detail: 'Rate limit exceeded.'
                );
            }
        });

        // 500 Internal Server Error - Database/Query exceptions
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                ProblemReporter::report($e, ProblemType::INTERNAL_ERROR, 'Database error during API request', [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ]);

                return ProblemResponseFactory::make(
                    ProblemType::INTERNAL_ERROR,
                    detail: ProblemDetailResolver::resolve($e, ProblemType::INTERNAL_ERROR)
                );
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                ProblemReporter::report($e, ProblemType::SERVICE_UNAVAILABLE, 'Service unavailable during API request');

                return ProblemResponseFactory::make(
                    ProblemType::SERVICE_UNAVAILABLE,
                    detail: ProblemDetailResolver::resolve($e, ProblemType::SERVICE_UNAVAILABLE, allowMessage: true)
                );
            }
        });
    })->create();
