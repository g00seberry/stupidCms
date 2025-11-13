<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Routing\PathNormalizer;
use App\Domain\Routing\PathReservationService;
use App\Domain\Routing\PathReservationServiceImpl;
use App\Domain\Routing\PathReservationStore;
use App\Domain\Routing\PathReservationStoreImpl;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для PathReservationService.
 *
 * Регистрирует PathReservationStore и PathReservationService как singleton.
 * PathReservationService работает только с путями из конфига (kind='path').
 * Таблица reserved_routes используется для fallback-роутера и валидации slug'ов.
 *
 * @package App\Providers
 */
class PathReservationServiceProvider extends ServiceProvider
{
    /**
     * Зарегистрировать сервисы.
     *
     * Регистрирует PathReservationStore и PathReservationService.
     * Загружает статические пути из конфига stupidcms.reserved_routes.paths.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(PathReservationStore::class, PathReservationStoreImpl::class);

        $this->app->singleton(PathReservationService::class, function ($app) {
            $config = config('stupidcms.reserved_routes', []);
            $staticPaths = [];
            
            /**
             * Контракт: PathReservationService работает только с путями из конфига (kind='path').
             * Таблица reserved_routes (из задачи 23) используется для fallback-роутера и валидации слугов.
             * PathReservationService предназначен для динамических/временных резервирований плагинов.
             * 
             * При проверке слугов (Entry) используется ReservedRouteRegistry (задача 23),
             * который объединяет конфиг и БД reserved_routes.
             */
            if (isset($config['paths'])) {
                foreach ($config['paths'] as $path) {
                    try {
                        $staticPaths[] = PathNormalizer::normalize($path);
                    } catch (\App\Domain\Routing\Exceptions\InvalidPathException $e) {
                        // Пропускаем невалидные пути из конфига (логируем, но не падаем)
                        \Log::warning("Invalid static path in config: {$path}", ['exception' => $e]);
                    }
                }
            }
            
            return new PathReservationServiceImpl(
                $app->make(PathReservationStore::class),
                $staticPaths
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

