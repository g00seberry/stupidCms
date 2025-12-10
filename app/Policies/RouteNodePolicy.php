<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RouteNode;
use App\Models\User;

/**
 * Политика авторизации для RouteNode.
 *
 * Определяет права доступа к узлам маршрутов на основе
 * административных разрешений пользователя (manage.routes).
 *
 * @package App\Policies
 */
class RouteNodePolicy
{
    /**
     * Определить, может ли пользователь управлять маршрутами.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function manage(User $user): bool
    {
        return $user->hasAdminPermission('manage.routes');
    }

    /**
     * Определить, может ли пользователь просматривать любые узлы маршрутов.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь просматривать узел маршрута.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return bool
     */
    public function view(User $user, RouteNode $routeNode): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь создавать узлы маршрутов.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @return bool
     */
    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь обновлять узел маршрута.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return bool
     */
    public function update(User $user, RouteNode $routeNode): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь удалять узел маршрута.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return bool
     */
    public function delete(User $user, RouteNode $routeNode): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь восстанавливать узел маршрута.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return bool
     */
    public function restore(User $user, RouteNode $routeNode): bool
    {
        return $this->manage($user);
    }

    /**
     * Определить, может ли пользователь окончательно удалять узел маршрута.
     *
     * Требует права 'manage.routes'.
     *
     * @param \App\Models\User $user Пользователь
     * @param \App\Models\RouteNode $routeNode Узел маршрута
     * @return bool
     */
    public function forceDelete(User $user, RouteNode $routeNode): bool
    {
        return $this->manage($user);
    }
}

