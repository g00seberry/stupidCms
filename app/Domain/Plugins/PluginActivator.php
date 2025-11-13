<?php

declare(strict_types=1);

namespace App\Domain\Plugins;

use App\Domain\Plugins\Events\PluginDisabled;
use App\Domain\Plugins\Events\PluginEnabled;
use App\Domain\Plugins\Exceptions\PluginAlreadyDisabledException;
use App\Domain\Plugins\Exceptions\PluginAlreadyEnabledException;
use App\Models\Plugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Активатор плагинов.
 *
 * Управляет включением и отключением плагинов с транзакционной безопасностью
 * и автоматической перезагрузкой маршрутов.
 *
 * @package App\Domain\Plugins
 */
final class PluginActivator
{
    /**
     * @param \App\Domain\Plugins\Services\PluginsRouteReloader $routeReloader Перезагрузчик маршрутов
     */
    public function __construct(
        private readonly Services\PluginsRouteReloader $routeReloader,
    ) {
    }

    /**
     * Включить плагин.
     *
     * Обновляет статус плагина в БД, отправляет событие и перезагружает маршруты.
     *
     * @param \App\Models\Plugin $plugin Плагин для включения
     * @return \App\Models\Plugin Обновлённый плагин
     * @throws \App\Domain\Plugins\Exceptions\PluginAlreadyEnabledException Если плагин уже включён
     */
    public function enable(Plugin $plugin): Plugin
    {
        if ($plugin->enabled) {
            throw PluginAlreadyEnabledException::forSlug($plugin->slug);
        }

        $updated = DB::transaction(function () use ($plugin): Plugin {
            $plugin->forceFill(['enabled' => true])->save();

            Event::dispatch(new PluginEnabled($plugin));

            return $plugin->refresh();
        });

        $this->routeReloader->reload();

        return $updated;
    }

    /**
     * Отключить плагин.
     *
     * Обновляет статус плагина в БД, отправляет событие и перезагружает маршруты.
     *
     * @param \App\Models\Plugin $plugin Плагин для отключения
     * @return \App\Models\Plugin Обновлённый плагин
     * @throws \App\Domain\Plugins\Exceptions\PluginAlreadyDisabledException Если плагин уже отключён
     */
    public function disable(Plugin $plugin): Plugin
    {
        if (! $plugin->enabled) {
            throw PluginAlreadyDisabledException::forSlug($plugin->slug);
        }

        $updated = DB::transaction(function () use ($plugin): Plugin {
            $plugin->forceFill(['enabled' => false])->save();

            Event::dispatch(new PluginDisabled($plugin));

            return $plugin->refresh();
        });

        $this->routeReloader->reload();

        return $updated;
    }
}

