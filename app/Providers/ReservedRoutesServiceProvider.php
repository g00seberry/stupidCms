<?php

namespace App\Providers;

use App\Domain\Routing\ReservedRouteRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

class ReservedRoutesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ReservedRouteRegistry::class, function ($app) {
            return new ReservedRouteRegistry(
                $app->make(CacheRepository::class),
                config('stupidcms')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

