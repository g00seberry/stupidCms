<?php

use App\Support\Http\HttpProblemException;
use App\Support\Http\ProblemDetailResolver;
use App\Support\Http\ProblemResponseFactory;
use App\Support\Http\ProblemType;
use App\Support\Logging\ProblemReporter;
use App\Support\Problems\Problem;
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
        $exceptions->render(function (\Throwable $e, $request) {
            if (! ($request->expectsJson() || $request->is('api/*'))) {
                return null;
            }

            if ($e instanceof HttpProblemException) {
                $response = ProblemResponseFactory::make($e->problem());

                return $e->apply($response);
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->filter()->first();

                $problem = Problem::of(ProblemType::VALIDATION_ERROR)
                    ->detail($firstError ?? ProblemType::VALIDATION_ERROR->defaultDetail())
                    ->extensions(['errors' => $errors]);

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $problem = Problem::of(ProblemType::UNAUTHORIZED)
                    ->detail($e->getMessage() ?: ProblemType::UNAUTHORIZED->defaultDetail());

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $problem = Problem::of(ProblemType::FORBIDDEN)
                    ->detail($e->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail());

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                $problem = Problem::of(ProblemType::FORBIDDEN)
                    ->detail($e->getMessage() ?: ProblemType::FORBIDDEN->defaultDetail());

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                $problem = Problem::of(ProblemType::NOT_FOUND)
                    ->detail('The requested resource was not found.');

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                $problem = Problem::of(ProblemType::RATE_LIMIT_EXCEEDED)
                    ->detail('Rate limit exceeded.');

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Illuminate\Database\QueryException) {
                ProblemReporter::report($e, ProblemType::INTERNAL_ERROR, 'Database error during API request', [
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ]);

                $problem = Problem::of(ProblemType::INTERNAL_ERROR)
                    ->detail(ProblemDetailResolver::resolve($e, ProblemType::INTERNAL_ERROR));

                return ProblemResponseFactory::make($problem);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException) {
                ProblemReporter::report($e, ProblemType::SERVICE_UNAVAILABLE, 'Service unavailable during API request');

                $problem = Problem::of(ProblemType::SERVICE_UNAVAILABLE)
                    ->detail(ProblemDetailResolver::resolve($e, ProblemType::SERVICE_UNAVAILABLE, allowMessage: true));

                return ProblemResponseFactory::make($problem);
            }

            return null;
        });
    })->create();
