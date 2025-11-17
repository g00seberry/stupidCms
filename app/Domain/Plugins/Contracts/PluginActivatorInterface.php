<?php

declare(strict_types=1);

namespace App\Domain\Plugins\Contracts;

use App\Models\Plugin;

/**
 * Контракт для активации/деактивации плагинов.
 *
 * @package App\Domain\Plugins\Contracts
 */
interface PluginActivatorInterface
{
    /**
     * Включить плагин.
     *
     * @param \App\Models\Plugin $plugin Плагин для включения
     * @return \App\Models\Plugin Обновлённый плагин
     * @throws \App\Domain\Plugins\Exceptions\PluginAlreadyEnabledException Если плагин уже включён
     */
    public function enable(Plugin $plugin): Plugin;

    /**
     * Отключить плагин.
     *
     * @param \App\Models\Plugin $plugin Плагин для отключения
     * @return \App\Models\Plugin Обновлённый плагин
     * @throws \App\Domain\Plugins\Exceptions\PluginAlreadyDisabledException Если плагин уже отключён
     */
    public function disable(Plugin $plugin): Plugin;
}

