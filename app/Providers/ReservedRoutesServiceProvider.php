<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Routing\ReservedRouteRegistry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для ReservedRouteRegistry.
 *
 * Регистрирует ReservedRouteRegistry как singleton.
 * ReservedRouteRegistry объединяет зарезервированные пути из конфига и БД.
 *
 * @package App\Providers
 */
class ReservedRoutesServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы.
     *
     * Регистрирует ReservedRouteRegistry как singleton с кэшем и конфигом.
     *
     * @return void
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
     * Загрузить сервисы.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}

