<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Services;

use App\Domain\Plugins\Contracts\RouteReloader;
use App\Domain\Plugins\Events\PluginsRoutesReloaded;
use App\Domain\Plugins\Exceptions\RoutesReloadFailed;
use App\Domain\Plugins\PluginRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Перезагрузчик маршрутов плагинов.
 *
 * Очищает кэш маршрутов, регистрирует автозагрузку, регистрирует провайдеры
 * включённых плагинов и кэширует маршруты (если включено).
 *
 * @package App\Domain\Plugins\Services
 */
final class PluginsRouteReloader implements RouteReloader
{
    /**
     * @param \Illuminate\Contracts\Foundation\Application $app Приложение Laravel
     * @param \App\Domain\Plugins\PluginRegistry $registry Реестр плагинов
     * @param \App\Domain\Plugins\Services\PluginAutoloader $autoloader Автозагрузчик классов
     */
    public function __construct(
        private readonly Application $app,
        private readonly PluginRegistry $registry,
        private readonly PluginAutoloader $autoloader,
    ) {
    }

    /**
     * Перезагрузить маршруты плагинов.
     *
     * Процесс:
     * 1. Очищает кэш маршрутов
     * 2. Регистрирует автозагрузку для включённых плагинов
     * 3. Регистрирует Service Providers плагинов
     * 4. Кэширует маршруты (если включено в конфиге)
     * 5. Отправляет событие PluginsRoutesReloaded
     *
     * @return void
     * @throws \App\Domain\Plugins\Exceptions\RoutesReloadFailed Если перезагрузка не удалась
     */
    public function reload(): void
    {
        try {
            Artisan::call('route:clear');

            $plugins = $this->registry->enabled();

            $this->autoloader->register($plugins);

            foreach ($plugins as $plugin) {
                $this->app->register($plugin->provider_fqcn, true);
            }

            if (config('plugins.auto_route_cache')) {
                Artisan::call('route:cache');
            }

            Event::dispatch(new PluginsRoutesReloaded(
                $plugins->pluck('provider_fqcn')->filter()->values()->all()
            ));
        } catch (Throwable $exception) {
            report($exception);

            throw RoutesReloadFailed::from($exception);
        }
    }
}

