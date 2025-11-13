<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Plugin;
use App\Models\User;

/**
 * Политика авторизации для Plugin.
 *
 * Определяет права доступа к плагинам на основе
 * административных разрешений пользователя (plugins.*).
 *
 * @package App\Policies
 */
final class PluginPolicy
{
    /**
     * Определить, может ли пользователь просматривать любые плагины.
     *
     * Требует права 'plugins.read'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAdminPermission('plugins.read');
    }

    /**
     * Определить, может ли пользователь включать/отключать плагин.
     *
     * Требует права 'plugins.toggle'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\Plugin $plugin Плагин
     * @return bool
     */
    public function toggle(User $user, Plugin $plugin): bool
    {
        return $user->hasAdminPermission('plugins.toggle');
    }

    /**
     * Определить, может ли пользователь синхронизировать плагины.
     *
     * Требует права 'plugins.sync'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function sync(User $user): bool
    {
        return $user->hasAdminPermission('plugins.sync');
    }
}

