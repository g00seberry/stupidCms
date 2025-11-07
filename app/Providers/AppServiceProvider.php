<?php

namespace App\Providers;

use App\Domain\Options\OptionsRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Entry::observe(EntryObserver::class);
    }
}
