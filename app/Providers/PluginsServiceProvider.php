<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Plugins\PluginRegistry;
use App\Domain\Plugins\Services\PluginAutoloader;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider для плагинов.
 *
 * Загружает и регистрирует провайдеры включённых плагинов.
 * Использует PluginAutoloader для регистрации классов плагинов.
 *
 * @package App\Providers
 */
final class PluginsServiceProvider extends ServiceProvider
{
    /**
     * Загрузить сервисы плагинов.
     *
     * Получает список включённых плагинов из реестра,
     * регистрирует их классы через PluginAutoloader
     * и регистрирует провайдеры плагинов.
     *
     * @param \App\Domain\Plugins\PluginRegistry $registry Реестр плагинов
     * @param \App\Domain\Plugins\Services\PluginAutoloader $autoloader Автозагрузчик классов плагинов
     * @return void
     */
    public function boot(PluginRegistry $registry, PluginAutoloader $autoloader): void
    {
        $plugins = $registry->enabled();

        $autoloader->register($plugins);

        foreach ($plugins as $plugin) {
            $this->app->register($plugin->provider_fqcn);
        }
    }
}

