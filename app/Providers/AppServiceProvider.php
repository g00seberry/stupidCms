<?php

namespace App\Providers;

use App\Domain\Options\OptionsRepository;
use App\Domain\View\BladeTemplateResolver;
use App\Domain\View\TemplateResolver;
use App\Models\Entry;
use App\Observers\EntryObserver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);
    }
}
