<?php

namespace App\Providers;

use App\Domain\Auth\JwtService;
use App\Domain\Auth\RefreshTokenRepository;
use App\Domain\Auth\RefreshTokenRepositoryImpl;
use App\Domain\Options\OptionsRepository;
use App\Domain\Sanitizer\RichTextSanitizer;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Observers\EntryObserver;
use App\Support\Errors\ErrorFactory;
use App\Support\Errors\ErrorKernel;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрация OptionsRepository
        $this->app->singleton(OptionsRepository::class, function ($app) {
            return new OptionsRepository($app->make(CacheRepository::class));
        });

        // Регистрация TemplateResolver
        // Используем scoped вместо singleton для совместимости с Octane/Swoole
        // Это гарантирует, что мемоизация View::exists() не протекает между запросами
        $this->app->scoped(TemplateResolver::class, function () {
            return new BladeTemplateResolver(
                default: config('view_templates.default', 'pages.show'),
                overridePrefix: config('view_templates.override_prefix', 'pages.overrides.'),
                typePrefix: config('view_templates.type_prefix', 'pages.types.'),
            );
        });

        // Регистрация RichTextSanitizer
        $this->app->singleton(RichTextSanitizer::class);

        // Регистрация JwtService
        $this->app->singleton(JwtService::class, function () {
            return new JwtService(config('jwt'));
        });

        // Регистрация RefreshTokenRepository
        $this->app->singleton(RefreshTokenRepository::class, RefreshTokenRepositoryImpl::class);

        // ErrorKernel — единая точка обработки ошибок API
        $this->app->singleton(ErrorKernel::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = config('errors');

            return ErrorKernel::fromConfig($config, $app);
        });

        $this->app->singleton(ErrorFactory::class, static fn ($app): ErrorFactory => $app->make(ErrorKernel::class)->factory());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);
        
        // Создаем директорию для кэша HTMLPurifier (idempotent)
        app('files')->ensureDirectoryExists(storage_path('app/purifier'));

        // Set JWT leeway to account for clock drift between server and client
        // This ensures stable token verification when there are small time differences
        \Firebase\JWT\JWT::$leeway = (int) config('jwt.leeway', 5); // Default: 5 seconds
    }
}
